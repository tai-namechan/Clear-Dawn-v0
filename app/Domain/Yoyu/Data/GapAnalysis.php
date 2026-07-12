<?php

namespace App\Domain\Yoyu\Data;

use Carbon\CarbonImmutable;

final readonly class GapAnalysis
{
    /**
     * @param  list<array{start: CarbonImmutable, end: CarbonImmutable}>  $mergedBusyIntervals
     * @param  list<GapSlot>  $allGaps
     * @param  list<GapSlot>  $suggestibleGaps
     * @param  array{cancelled: int, transparent: int, all_day: int, invalid: int}  $ignoredEventCounts
     */
    public function __construct(
        public array $mergedBusyIntervals,
        public int $totalBusyMinutes,
        public array $allGaps,
        public array $suggestibleGaps,
        public array $ignoredEventCounts,
    ) {}

    /**
     * @return array{
     *     busy_minutes: int,
     *     gaps: list<array{key: string, start: string, end: string, minutes: int}>,
     *     all_gaps: list<array{key: string, start: string, end: string, minutes: int}>,
     *     ignored: array{cancelled: int, transparent: int, all_day: int, invalid: int}
     * }
     */
    public function toArray(string $timezone): array
    {
        return [
            'busy_minutes' => $this->totalBusyMinutes,
            'gaps' => array_map(
                fn (GapSlot $gap): array => $gap->toArray($timezone),
                $this->suggestibleGaps,
            ),
            'all_gaps' => array_map(
                fn (GapSlot $gap): array => $gap->toArray($timezone),
                $this->allGaps,
            ),
            'ignored' => $this->ignoredEventCounts,
        ];
    }
}
