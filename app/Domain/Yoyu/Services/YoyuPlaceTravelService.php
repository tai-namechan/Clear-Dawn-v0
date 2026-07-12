<?php

namespace App\Domain\Yoyu\Services;

use App\Domain\Yoyu\Models\YoyuCalendarEvent;
use App\Domain\Yoyu\Models\YoyuPlace;
use App\Domain\Yoyu\Support\PlaceNameNormalizer;

class YoyuPlaceTravelService
{
    /**
     * Build a map of normalized place name => travel_minutes for one user.
     * Places per user are expected to stay small (tens), so one query is enough.
     *
     * @return array<string, int>
     */
    public function travelMinutesByNormalizedName(int $userId): array
    {
        $map = [];

        YoyuPlace::query()
            ->where('user_id', $userId)
            ->get(['name', 'travel_minutes'])
            ->each(function (YoyuPlace $place) use (&$map): void {
                $key = PlaceNameNormalizer::normalize($place->name);

                if ($key === '') {
                    return;
                }

                $map[$key] = (int) $place->travel_minutes;
            });

        return $map;
    }

    /**
     * Resolve travel minutes for a calendar location string.
     *
     * @param  array<string, int>  $travelByNormalizedName
     */
    public function resolveMinutes(?string $location, array $travelByNormalizedName): ?int
    {
        $key = PlaceNameNormalizer::normalize((string) $location);

        if ($key === '' || ! array_key_exists($key, $travelByNormalizedName)) {
            return null;
        }

        return $travelByNormalizedName[$key];
    }

    /**
     * Upsert by normalized name match within the user scope.
     * When $externalId is given and the cached event has empty location,
     * fill location so travel resolves on the next Today load.
     */
    public function upsert(
        int $userId,
        string $name,
        int $travelMinutes,
        ?string $externalId = null,
    ): YoyuPlace {
        $trimmed = trim($name);
        $key = PlaceNameNormalizer::normalize($trimmed);

        /** @var YoyuPlace|null $existing */
        $existing = YoyuPlace::query()
            ->where('user_id', $userId)
            ->get()
            ->first(fn (YoyuPlace $place): bool => PlaceNameNormalizer::normalize($place->name) === $key);

        if ($existing !== null) {
            $existing->update(['travel_minutes' => $travelMinutes]);
            $place = $existing->refresh();
        } else {
            $place = YoyuPlace::query()->create([
                'user_id' => $userId,
                'name' => $trimmed,
                'travel_minutes' => $travelMinutes,
            ]);
        }

        if ($externalId !== null && $externalId !== '') {
            YoyuCalendarEvent::query()
                ->withoutUserScope()
                ->where('user_id', $userId)
                ->where('external_id', $externalId)
                ->where(function ($query): void {
                    $query->whereNull('location')->orWhere('location', '');
                })
                ->limit(1)
                ->update(['location' => $trimmed]);
        }

        return $place;
    }
}
