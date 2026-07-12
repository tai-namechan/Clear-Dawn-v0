<?php

namespace App\Domain\Yoyu\Services;

use App\Domain\Yoyu\Models\YoyuCalendarEvent;
use App\Domain\Yoyu\Models\YoyuPlace;
use App\Domain\Yoyu\Support\PlaceNameNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

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
            ->get(['normalized_name', 'travel_minutes'])
            ->each(function (YoyuPlace $place) use (&$map): void {
                $key = (string) $place->normalized_name;

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
     * Atomic upsert by (user_id, normalized_name). Display name is set on create only.
     * When $externalId is given and the cached event has empty Google location,
     * write location_override (never provider location).
     */
    public function upsert(
        int $userId,
        string $name,
        int $travelMinutes,
        ?string $externalId = null,
    ): YoyuPlace {
        $trimmed = trim($name);
        $key = PlaceNameNormalizer::normalize($trimmed);

        try {
            $place = $this->upsertPlaceRow($userId, $trimmed, $key, $travelMinutes);
        } catch (UniqueConstraintViolationException|QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }

            $place = $this->upsertPlaceRow($userId, $trimmed, $key, $travelMinutes);
        }

        if ($externalId !== null && $externalId !== '') {
            $this->applyLocationOverride($userId, $externalId, $trimmed);
        }

        return $place;
    }

    private function upsertPlaceRow(
        int $userId,
        string $trimmed,
        string $key,
        int $travelMinutes,
    ): YoyuPlace {
        /** @var YoyuPlace|null $existing */
        $existing = YoyuPlace::query()
            ->where('user_id', $userId)
            ->where('normalized_name', $key)
            ->first();

        if ($existing !== null) {
            $existing->update(['travel_minutes' => $travelMinutes]);

            return $existing->refresh();
        }

        try {
            return YoyuPlace::query()->create([
                'user_id' => $userId,
                'name' => $trimmed,
                'normalized_name' => $key,
                'travel_minutes' => $travelMinutes,
            ]);
        } catch (UniqueConstraintViolationException|QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }

            /** @var YoyuPlace $winner */
            $winner = YoyuPlace::query()
                ->where('user_id', $userId)
                ->where('normalized_name', $key)
                ->firstOrFail();

            $winner->update(['travel_minutes' => $travelMinutes]);

            return $winner->refresh();
        }
    }

    /**
     * Persist app-owned override only when Google location is empty.
     * Never writes provider `location`. Always scoped by user_id.
     */
    private function applyLocationOverride(int $userId, string $externalId, string $placeName): void
    {
        YoyuCalendarEvent::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->where('external_id', $externalId)
            ->where(function ($query): void {
                $query->whereNull('location')->orWhere('location', '');
            })
            ->limit(1)
            ->update(['location_override' => $placeName]);
    }

    private function isUniqueViolation(UniqueConstraintViolationException|QueryException $e): bool
    {
        if ($e instanceof UniqueConstraintViolationException) {
            return true;
        }

        $message = strtolower($e->getMessage());

        return str_contains($message, 'unique')
            || str_contains($message, 'duplicate')
            || (string) $e->getCode() === '23000';
    }
}
