<?php

namespace App\Services;

use App\Models\DailyCheckin;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UpsertDailyCheckinService
{
    /**
     * @param  array{
     *     sleep_quality?: int|null,
     *     fatigue?: int|null,
     *     muscle_soreness?: int|null,
     *     stress?: int|null,
     *     mood?: int|null,
     *     region_tension?: array<string, int>|null,
     *     readiness_self?: int|null,
     *     note?: string|null
     * }  $attributes
     */
    public function handle(User $user, Carbon $date, array $attributes): DailyCheckin
    {
        return DB::transaction(function () use ($user, $date, $attributes): DailyCheckin {
            /** @var DailyCheckin $checkin */
            $checkin = DailyCheckin::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'checked_on' => $date->toDateString(),
                ],
                [
                    'sleep_quality' => $attributes['sleep_quality'] ?? null,
                    'fatigue' => $attributes['fatigue'] ?? null,
                    'muscle_soreness' => $attributes['muscle_soreness'] ?? null,
                    'stress' => $attributes['stress'] ?? null,
                    'mood' => $attributes['mood'] ?? null,
                    'region_tension' => $attributes['region_tension'] ?? null,
                    'readiness_self' => $attributes['readiness_self'] ?? null,
                    'note' => $attributes['note'] ?? null,
                ],
            );

            return $checkin;
        });
    }
}
