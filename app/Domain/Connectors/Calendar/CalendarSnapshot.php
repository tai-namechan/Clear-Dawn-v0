<?php

namespace App\Domain\Connectors\Calendar;

use Carbon\CarbonImmutable;

final readonly class CalendarSnapshot
{
    /**
     * @param  list<CalendarEventData>  $events
     */
    public function __construct(
        public CalendarConnectionStatus $connectionStatus,
        public array $events = [],
        public ?CarbonImmutable $syncedAt = null,
        public bool $isStale = false,
        public ?string $warningCode = null,
        public ?string $accountEmail = null,
    ) {}

    public static function empty(CalendarConnectionStatus $status, ?string $warningCode = null): self
    {
        return new self(connectionStatus: $status, warningCode: $warningCode);
    }

    /**
     * @return list<CalendarEventData>
     */
    public function timedEvents(): array
    {
        return array_values(array_filter(
            $this->events,
            fn (CalendarEventData $event): bool => ! $event->allDay
                && ! $event->isCancelled()
                && $event->startsAt !== null
                && $event->endsAt !== null,
        ));
    }

    /**
     * @return list<string>
     */
    public function allDayTitles(): array
    {
        return array_values(array_map(
            fn (CalendarEventData $event): string => $event->title,
            array_filter(
                $this->events,
                fn (CalendarEventData $event): bool => $event->allDay && ! $event->isCancelled(),
            ),
        ));
    }
}
