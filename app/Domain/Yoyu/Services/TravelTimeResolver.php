<?php

namespace App\Domain\Yoyu\Services;

use App\Domain\Connectors\Calendar\CalendarEventData;
use App\Models\User;

/**
 * Resolve travel_min from yoyu_places via YoyuPlaceTravelService (one query).
 * Never invents 0 for unresolved locations.
 */
final class TravelTimeResolver
{
    public function __construct(private YoyuPlaceTravelService $places) {}

    /**
     * @param  list<CalendarEventData>  $events
     * @return list<CalendarEventData>
     */
    public function resolve(User $user, array $events): array
    {
        $map = $this->places->travelMinutesByNormalizedName((int) $user->id);

        return array_map(
            function (CalendarEventData $event) use ($map): CalendarEventData {
                $travel = $this->places->resolveMinutes($event->location, $map);

                return $event->withTravelMin($travel);
            },
            $events,
        );
    }
}
