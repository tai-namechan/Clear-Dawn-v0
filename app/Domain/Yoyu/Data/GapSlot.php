<?php

namespace App\Domain\Yoyu\Data;

use Carbon\CarbonImmutable;

final readonly class GapSlot
{
    public function __construct(
        public string $key,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public int $minutes,
    ) {}

    /**
     * @return array{key: string, start: string, end: string, minutes: int}
     */
    public function toArray(string $timezone): array
    {
        return [
            'key' => $this->key,
            'start' => $this->start->timezone($timezone)->format('H:i'),
            'end' => $this->end->timezone($timezone)->format('H:i'),
            'minutes' => $this->minutes,
        ];
    }
}
