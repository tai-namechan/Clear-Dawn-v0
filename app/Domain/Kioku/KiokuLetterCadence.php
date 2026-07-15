<?php

namespace App\Domain\Kioku;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

enum KiokuLetterCadence: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';

    public function maxItems(): int
    {
        return match ($this) {
            self::Daily => 2,
            self::Weekly => 5,
        };
    }

    public function dedupeKeyFor(CarbonInterface $deliveryDate): string
    {
        $day = CarbonImmutable::instance($deliveryDate)->startOfDay();

        return match ($this) {
            self::Daily => 'daily:'.$day->toDateString(),
            self::Weekly => 'weekly:'.$day->startOfWeek()->toDateString(),
        };
    }

    public function weekStartFor(CarbonInterface $deliveryDate): CarbonImmutable
    {
        return CarbonImmutable::instance($deliveryDate)->startOfDay()->startOfWeek();
    }
}
