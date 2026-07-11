<?php

namespace App\Domain\Connectors\Jobs;

use App\Domain\Connectors\Calendar\CalendarEventData;
use App\Domain\Connectors\Google\GoogleCalendarApiClient;
use App\Domain\Connectors\Google\GoogleTokenManager;
use App\Domain\Connectors\Google\ReauthorizationRequiredException;
use App\Domain\Connectors\Google\UnauthorizedGoogleRequestException;
use App\Domain\Kioku\Models\Connector;
use App\Domain\Yoyu\Models\YoyuCalendarEvent;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncGoogleCalendarJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 300];

    public int $uniqueFor = 900;

    public function __construct(public string $connectorId)
    {
        $this->onQueue('integrations');
    }

    public function uniqueId(): string
    {
        return $this->connectorId;
    }

    public function handle(GoogleTokenManager $tokens, GoogleCalendarApiClient $api): void
    {
        $connector = Connector::query()->withoutUserScope()->find($this->connectorId);
        if ($connector === null || $connector->source_type !== Connector::SOURCE_GOOGLE_CALENDAR) {
            return;
        }

        if (in_array($connector->status, ['revoking'], true)) {
            return;
        }

        $connector->update([
            'status' => 'syncing',
            'last_sync_attempt_at' => now(),
        ]);

        $timezone = (string) config('app.timezone', 'UTC');
        $pastDays = (int) config('calendar.sync_past_days', 1);
        $futureDays = (int) config('calendar.sync_future_days', 7);
        $windowStart = CarbonImmutable::now($timezone)->startOfDay()->subDays($pastDays);
        // timeMax is exclusive: the day after the last synced day, at 00:00.
        $windowEnd = CarbonImmutable::now($timezone)->startOfDay()->addDays($futureDays + 1);

        try {
            $accessToken = $tokens->validAccessToken($connector);

            try {
                $events = $api->listPrimaryEvents($accessToken, $windowStart, $windowEnd, $timezone);
            } catch (UnauthorizedGoogleRequestException) {
                // Token may have just been revoked/rotated: refresh once, retry once.
                $connector->update(['token_expires_at' => now()->subMinute()]);
                $accessToken = $tokens->validAccessToken($connector);
                $events = $api->listPrimaryEvents($accessToken, $windowStart, $windowEnd, $timezone);
            }

            $this->persist($connector, $events, $windowStart, $windowEnd, $timezone);

            $connector->update([
                'status' => 'connected',
                'last_synced_at' => now(),
                'last_error_code' => null,
                'last_error_at' => null,
            ]);
        } catch (ReauthorizationRequiredException) {
            // Terminal until the user reconnects; token manager already
            // recorded the error state. Existing cache stays untouched.
            return;
        } catch (Throwable $e) {
            $connector->update([
                'status' => 'error',
                'last_error_code' => 'sync_failed',
                'last_error_at' => now(),
            ]);

            Log::warning('Google Calendar sync failed.', [
                'connector_id' => $this->connectorId,
                'attempt' => $this->attempts(),
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Fetched pages are normalized outside any transaction; only the final
     * cache swap runs inside one.
     *
     * @param  list<CalendarEventData>  $events
     */
    private function persist(
        Connector $connector,
        array $events,
        CarbonImmutable $windowStart,
        CarbonImmutable $windowEnd,
        string $timezone,
    ): void {
        $seenIds = [];
        $now = now();

        DB::transaction(function () use ($connector, $events, $windowStart, $windowEnd, $timezone, &$seenIds, $now): void {
            foreach ($events as $event) {
                $seenIds[] = $event->externalId;

                if ($event->isCancelled()) {
                    // Update existing rows to cancelled; never insert new cancelled rows.
                    YoyuCalendarEvent::query()
                        ->withoutUserScope()
                        ->where('connector_id', $connector->id)
                        ->where('calendar_external_id', 'primary')
                        ->where('external_id', $event->externalId)
                        ->update(['status' => 'cancelled', 'synced_at' => $now]);

                    continue;
                }

                YoyuCalendarEvent::query()->withoutUserScope()->updateOrCreate(
                    [
                        'connector_id' => $connector->id,
                        'calendar_external_id' => 'primary',
                        'external_id' => $event->externalId,
                    ],
                    [
                        'user_id' => $connector->user_id,
                        'title' => $event->title,
                        'all_day' => $event->allDay,
                        'starts_at' => $event->startsAt?->toDateTimeString(),
                        'ends_at' => $event->endsAt?->toDateTimeString(),
                        'starts_on' => $event->startsOn,
                        'ends_on' => $event->endsOn,
                        'event_timezone' => $event->timezone,
                        'transparency' => $event->transparency,
                        'status' => $event->status,
                        'location' => $event->location,
                        'synced_at' => $now,
                    ],
                );
            }

            // Rows overlapping this window that Google no longer returns
            // (moved out / hard-deleted) must not linger as stale cache.
            $windowStartDate = $windowStart->timezone($timezone)->toDateString();
            $windowEndDate = $windowEnd->timezone($timezone)->toDateString();

            YoyuCalendarEvent::query()
                ->withoutUserScope()
                ->where('connector_id', $connector->id)
                ->where('calendar_external_id', 'primary')
                ->when($seenIds !== [], fn ($query) => $query->whereNotIn('external_id', $seenIds))
                ->where(function ($query) use ($windowStart, $windowEnd, $windowStartDate, $windowEndDate): void {
                    $query->where(function ($timed) use ($windowStart, $windowEnd): void {
                        $timed->where('all_day', false)
                            ->where('starts_at', '<', $windowEnd->utc()->toDateTimeString())
                            ->where('ends_at', '>', $windowStart->utc()->toDateTimeString());
                    })->orWhere(function ($allDay) use ($windowStartDate, $windowEndDate): void {
                        $allDay->where('all_day', true)
                            ->where('starts_on', '<', $windowEndDate)
                            ->where('ends_on', '>', $windowStartDate);
                    });
                })
                ->delete();
        });
    }

    public function failed(?Throwable $exception): void
    {
        Connector::query()
            ->withoutUserScope()
            ->whereKey($this->connectorId)
            ->where('status', 'syncing')
            ->update([
                'status' => 'error',
                'last_error_code' => 'sync_failed',
                'last_error_at' => now(),
            ]);
    }
}
