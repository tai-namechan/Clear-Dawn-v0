<?php

namespace App\Domain\Connectors\Calendar;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * No connector: empty schedule with a connect CTA. Never fabricates events.
 */
final class EmptyCalendarProvider implements CalendarProvider
{
    public function snapshotFor(
        User $user,
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $timezone,
    ): CalendarSnapshot {
        return CalendarSnapshot::empty(CalendarConnectionStatus::Disconnected, 'not_connected');
    }
}
