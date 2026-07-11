<?php

namespace App\Domain\Connectors\Calendar;

use App\Models\User;
use Carbon\CarbonImmutable;

interface CalendarProvider
{
    /**
     * Read-only snapshot for [$from, $to) — never performs external HTTP.
     */
    public function snapshotFor(
        User $user,
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $timezone,
    ): CalendarSnapshot;
}
