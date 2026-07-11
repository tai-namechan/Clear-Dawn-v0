<?php

namespace App\Domain\Connectors\Calendar;

use App\Domain\Kioku\Models\Connector;
use App\Domain\Yoyu\Models\YoyuCalendarEvent;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Reads the DB event cache only. Sync freshness is judged from
 * connectors.last_synced_at (an empty day is still a successful sync).
 */
final class CachedGoogleCalendarProvider implements CalendarProvider
{
    public function __construct(private readonly Connector $connector) {}

    public function snapshotFor(
        User $user,
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $timezone,
    ): CalendarSnapshot {
        if ((int) $this->connector->user_id !== (int) $user->id) {
            return CalendarSnapshot::empty(CalendarConnectionStatus::Disconnected);
        }

        $status = match ($this->connector->status) {
            'connected' => CalendarConnectionStatus::Connected,
            'syncing', 'idle' => CalendarConnectionStatus::Syncing,
            'revoking' => CalendarConnectionStatus::Revoking,
            default => CalendarConnectionStatus::Error,
        };

        $syncedAt = $this->connector->last_synced_at
            ? CarbonImmutable::instance($this->connector->last_synced_at)
            : null;

        // Never synced yet: no trustworthy cache to show.
        if ($syncedAt === null) {
            $warning = $status === CalendarConnectionStatus::Error ? 'reconnect_required' : 'sync_pending';

            return new CalendarSnapshot(
                connectionStatus: $status,
                warningCode: $warning,
                accountEmail: $this->connector->external_account_email,
            );
        }

        $events = $this->eventsBetween($user, $from, $to, $timezone);

        $ttlMinutes = (int) config('calendar.sync_ttl_minutes', 15);
        $isStale = $syncedAt->addMinutes($ttlMinutes)->isPast();

        $warning = null;
        if ($status === CalendarConnectionStatus::Error) {
            $warning = 'stale_cache_reconnect';
        } elseif ($isStale) {
            $warning = 'stale_cache';
        }

        return new CalendarSnapshot(
            connectionStatus: $status,
            events: $events,
            syncedAt: $syncedAt,
            isStale: $isStale,
            warningCode: $warning,
            accountEmail: $this->connector->external_account_email,
        );
    }

    /**
     * @return list<CalendarEventData>
     */
    private function eventsBetween(User $user, CarbonImmutable $from, CarbonImmutable $to, string $timezone): array
    {
        $fromDate = $from->timezone($timezone)->toDateString();
        $toDate = $to->timezone($timezone)->toDateString();

        return array_values(YoyuCalendarEvent::query()
            ->where('user_id', $user->id)
            ->where('connector_id', $this->connector->id)
            ->where(function ($query) use ($from, $to, $fromDate, $toDate): void {
                // Timed events overlapping [from, to)
                $query->where(function ($timed) use ($from, $to): void {
                    $timed->where('all_day', false)
                        ->where('starts_at', '<', $to->utc()->toDateTimeString())
                        ->where('ends_at', '>', $from->utc()->toDateTimeString());
                })
                    // All-day events overlapping [fromDate, toDate] (ends_on exclusive)
                    ->orWhere(function ($allDay) use ($fromDate, $toDate): void {
                        $allDay->where('all_day', true)
                            ->where('starts_on', '<=', $toDate)
                            ->where('ends_on', '>', $fromDate);
                    });
            })
            ->orderBy('starts_at')
            ->orderBy('starts_on')
            ->limit(200)
            ->get()
            ->map(fn (YoyuCalendarEvent $event): CalendarEventData => new CalendarEventData(
                externalId: $event->external_id,
                title: $event->title,
                allDay: $event->all_day,
                startsAt: $event->starts_at ? CarbonImmutable::instance($event->starts_at) : null,
                endsAt: $event->ends_at ? CarbonImmutable::instance($event->ends_at) : null,
                startsOn: $event->starts_on,
                endsOn: $event->ends_on,
                timezone: $event->event_timezone,
                status: $event->status,
                transparency: $event->transparency,
                location: $event->location,
            ))
            ->all());
    }
}
