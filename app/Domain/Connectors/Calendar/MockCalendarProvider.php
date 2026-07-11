<?php

namespace App\Domain\Connectors\Calendar;

use App\Domain\Yoyu\Support\MockCalendar;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Fixture events for local/testing only. The resolver must never select
 * this provider in staging/production (fabricated events would be treated
 * as facts by the briefing).
 */
final class MockCalendarProvider implements CalendarProvider
{
    public function snapshotFor(
        User $user,
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $timezone,
    ): CalendarSnapshot {
        $events = array_map(
            fn (array $event): CalendarEventData => new CalendarEventData(
                externalId: $event['id'],
                title: $event['title'],
                allDay: false,
                startsAt: CarbonImmutable::parse($event['start'])->utc(),
                endsAt: CarbonImmutable::parse($event['end'])->utc(),
                startsOn: null,
                endsOn: null,
                timezone: $timezone,
                location: $event['place'],
            ),
            MockCalendar::todayEvents(),
        );

        return new CalendarSnapshot(
            connectionStatus: CalendarConnectionStatus::Mock,
            events: $events,
            syncedAt: CarbonImmutable::now(),
        );
    }
}
