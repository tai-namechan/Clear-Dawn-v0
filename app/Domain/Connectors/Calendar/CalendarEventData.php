<?php

namespace App\Domain\Connectors\Calendar;

use Carbon\CarbonImmutable;

/**
 * Normalized calendar event. Timed events carry startsAt/endsAt (UTC);
 * all-day events carry startsOn/endsOn (local dates, end exclusive).
 */
final readonly class CalendarEventData
{
    public function __construct(
        public string $externalId,
        public string $title,
        public bool $allDay,
        public ?CarbonImmutable $startsAt,
        public ?CarbonImmutable $endsAt,
        public ?string $startsOn,
        public ?string $endsOn,
        public ?string $timezone,
        public string $status = 'confirmed',
        public string $transparency = 'opaque',
        public ?string $location = null,
        public int $travelMin = 0,
        public ?string $color = null,
    ) {}

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isTransparent(): bool
    {
        return $this->transparency === 'transparent';
    }

    /**
     * Legacy Yoyu Today shape ({id,title,start,end,place,travel_min,color}).
     * Only meaningful for timed events.
     *
     * @return array{id: string, title: string, start: string, end: string, place: string, travel_min: int, color: string}
     */
    public function toClientArray(string $timezone): array
    {
        return [
            'id' => $this->externalId,
            'title' => $this->title,
            'start' => (string) $this->startsAt?->timezone($timezone)->toIso8601String(),
            'end' => (string) $this->endsAt?->timezone($timezone)->toIso8601String(),
            'place' => (string) ($this->location ?? ''),
            'travel_min' => $this->travelMin,
            'color' => $this->color ?? '#4A7DC4',
        ];
    }
}
