<?php

namespace App\Domain\Shared\AI;

use Carbon\CarbonImmutable;

final class AiUsagePeriodResolver
{
    public function timezone(): string
    {
        $timezone = config('app.timezone', 'UTC');

        return is_string($timezone) && $timezone !== '' ? $timezone : 'UTC';
    }

    public function periodFor(?CarbonImmutable $at = null, ?string $timezone = null): string
    {
        $tz = $timezone ?? $this->timezone();
        $moment = ($at ?? CarbonImmutable::now('UTC'))->timezone($tz);

        return $moment->format('Y-m');
    }

    /**
     * Inclusive UTC bounds for a YYYY-MM period in the given timezone.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function utcBounds(string $period, ?string $timezone = null): array
    {
        $tz = $timezone ?? $this->timezone();

        if (preg_match('/^\d{4}-\d{2}$/', $period) !== 1) {
            throw new \InvalidArgumentException("Invalid period [{$period}].");
        }

        $startLocal = CarbonImmutable::parse($period.'-01 00:00:00', $tz);
        $endLocal = $startLocal->addMonth()->subSecond();

        return [
            $startLocal->utc(),
            $endLocal->utc(),
        ];
    }
}
