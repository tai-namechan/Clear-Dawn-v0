<?php

namespace App\Domain\Connectors\Google;

use App\Domain\Connectors\Calendar\CalendarEventData;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal events.list client (primary calendar, full-window sync).
 * No SDK: only the one endpoint Phase 1 needs.
 */
class GoogleCalendarApiClient
{
    private const EVENTS_ENDPOINT = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';

    private const MAX_PAGES = 10;

    /**
     * @return list<CalendarEventData>
     *
     * @throws UnauthorizedGoogleRequestException on 401 (caller refreshes once and retries)
     */
    public function listPrimaryEvents(
        string $accessToken,
        CarbonImmutable $timeMin,
        CarbonImmutable $timeMax,
        string $timezone,
    ): array {
        $events = [];
        $pageToken = null;
        $pages = 0;

        do {
            if (++$pages > self::MAX_PAGES) {
                throw new RuntimeException('Google Calendar pagination exceeded max pages.');
            }

            $response = Http::withToken($accessToken)
                ->connectTimeout(5)
                ->timeout(20)
                ->retry(3, 1000, function ($exception): bool {
                    if (! $exception instanceof RequestException || $exception->response === null) {
                        return false;
                    }
                    $status = $exception->response->status();

                    return $status === 429 || $status >= 500;
                }, throw: false)
                ->get(self::EVENTS_ENDPOINT, array_filter([
                    'singleEvents' => 'true',
                    'showDeleted' => 'true',
                    'orderBy' => 'startTime',
                    'timeMin' => $timeMin->toRfc3339String(),
                    'timeMax' => $timeMax->toRfc3339String(),
                    'timeZone' => $timezone,
                    'maxResults' => 250,
                    'pageToken' => $pageToken,
                ], fn ($value) => $value !== null));

            if ($response->status() === 401) {
                throw new UnauthorizedGoogleRequestException;
            }

            if ($response->failed()) {
                throw new RuntimeException('Google Calendar API failed: HTTP '.$response->status());
            }

            /** @var array{items?: list<array<string, mixed>>, nextPageToken?: string} $data */
            $data = $response->json() ?? [];

            foreach ($data['items'] ?? [] as $item) {
                $event = $this->normalize($item);
                if ($event !== null) {
                    $events[] = $event;
                }
            }

            $pageToken = $data['nextPageToken'] ?? null;
        } while ($pageToken !== null);

        return $events;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function normalize(array $item): ?CalendarEventData
    {
        $externalId = $item['id'] ?? null;
        if (! is_string($externalId) || $externalId === '') {
            return null;
        }

        $start = is_array($item['start'] ?? null) ? $item['start'] : [];
        $end = is_array($item['end'] ?? null) ? $item['end'] : [];

        $allDay = isset($start['date']);
        $startsAt = null;
        $endsAt = null;
        $startsOn = null;
        $endsOn = null;

        if ($allDay) {
            $startsOn = is_string($start['date'] ?? null) ? $start['date'] : null;
            $endsOn = is_string($end['date'] ?? null) ? $end['date'] : $startsOn;
            if ($startsOn === null) {
                return null;
            }
        } else {
            $startRaw = $start['dateTime'] ?? null;
            $endRaw = $end['dateTime'] ?? null;
            if (! is_string($startRaw) || ! is_string($endRaw)) {
                return null;
            }
            $startsAt = CarbonImmutable::parse($startRaw)->utc();
            $endsAt = CarbonImmutable::parse($endRaw)->utc();
        }

        return new CalendarEventData(
            externalId: $externalId,
            title: $this->cleanText($item['summary'] ?? null) ?: '(タイトルなし)',
            allDay: $allDay,
            startsAt: $startsAt,
            endsAt: $endsAt,
            startsOn: $startsOn,
            endsOn: $endsOn,
            timezone: is_string($start['timeZone'] ?? null) ? $start['timeZone'] : null,
            status: in_array($item['status'] ?? null, ['confirmed', 'tentative', 'cancelled'], true)
                ? $item['status']
                : 'confirmed',
            transparency: ($item['transparency'] ?? 'opaque') === 'transparent' ? 'transparent' : 'opaque',
            location: $this->cleanText($item['location'] ?? null),
        );
    }

    private function cleanText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        // Strip control characters; titles are rendered as text, never HTML.
        $clean = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value));

        return $clean === '' ? null : mb_substr($clean, 0, 255);
    }
}
