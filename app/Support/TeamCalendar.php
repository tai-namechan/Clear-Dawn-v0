<?php

namespace App\Support;

use App\Models\Team;
use Carbon\CarbonImmutable;

final class TeamCalendar
{
    public function timezone(Team $team): string
    {
        $timezone = is_string($team->timezone) && $team->timezone !== ''
            ? $team->timezone
            : 'Asia/Tokyo';

        try {
            new \DateTimeZone($timezone);
        } catch (\Exception) {
            return 'Asia/Tokyo';
        }

        return $timezone;
    }

    public function now(Team $team): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone($team));
    }

    public function today(Team $team): CarbonImmutable
    {
        return $this->now($team)->startOfDay();
    }

    /**
     * Inclusive calendar window ending today in the team timezone.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function dayWindow(Team $team, int $days): array
    {
        $today = $this->today($team);
        $start = $today->subDays(max($days, 1) - 1)->startOfDay();

        return [$start, $today->endOfDay()];
    }
}
