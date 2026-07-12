<?php

namespace App\Domain\Connectors\Calendar;

use Carbon\CarbonImmutable;

/**
 * Normalized calendar event. Timed events carry startsAt/endsAt (UTC);
 * all-day events carry startsOn/endsOn (local dates, end exclusive).
 *
 * travelMin is resolved from yoyu_places (null = unresolved — never fabricate 0).
 * prepMinutesOverride / bufferMinutesOverride are app-owned (null = user default).
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
        public ?int $travelMin = null,
        public ?string $color = null,
        public ?int $prepMinutesOverride = null,
        public ?int $bufferMinutesOverride = null,
    ) {}

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isTransparent(): bool
    {
        return $this->transparency === 'transparent';
    }

    public function effectivePrepMinutes(int $userDefault): int
    {
        return max(0, $this->prepMinutesOverride ?? $userDefault);
    }

    public function effectiveBufferMinutes(int $userDefault): int
    {
        return max(0, $this->bufferMinutesOverride ?? $userDefault);
    }

    public function withTravelMin(?int $travelMin): self
    {
        return new self(
            externalId: $this->externalId,
            title: $this->title,
            allDay: $this->allDay,
            startsAt: $this->startsAt,
            endsAt: $this->endsAt,
            startsOn: $this->startsOn,
            endsOn: $this->endsOn,
            timezone: $this->timezone,
            status: $this->status,
            transparency: $this->transparency,
            location: $this->location,
            travelMin: $travelMin,
            color: $this->color,
            prepMinutesOverride: $this->prepMinutesOverride,
            bufferMinutesOverride: $this->bufferMinutesOverride,
        );
    }

    /**
     * Legacy Yoyu Today shape.
     * Only meaningful for timed events.
     *
     * @return array{
     *     id: string,
     *     title: string,
     *     start: string,
     *     end: string,
     *     place: string,
     *     travel_min: int|null,
     *     color: string,
     *     prep_minutes_override: int|null,
     *     buffer_minutes_override: int|null
     * }
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
            'prep_minutes_override' => $this->prepMinutesOverride,
            'buffer_minutes_override' => $this->bufferMinutesOverride,
        ];
    }
}
