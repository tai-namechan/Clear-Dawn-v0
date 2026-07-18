<?php

namespace App\Services;

use App\Models\DailyCheckin;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UpsertDailyCheckinService
{
    private const UPDATABLE_FIELDS = [
        'sleep_quality',
        'fatigue',
        'muscle_soreness',
        'stress',
        'mood',
        'region_tension',
        'readiness_self',
        'note',
    ];

    /**
     * 部分更新: $attributes に含まれるキーのみ更新し、
     * 未送信のフィールド（note / region_tension 等）は既存値を保持する。
     *
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
        $values = array_intersect_key($attributes, array_flip(self::UPDATABLE_FIELDS));

        return DB::transaction(function () use ($user, $date, $values): DailyCheckin {
            $checkin = DailyCheckin::query()
                ->where('user_id', $user->id)
                ->whereDate('checked_on', $date->toDateString())
                ->first();

            if ($checkin === null) {
                $checkin = new DailyCheckin([
                    'user_id' => $user->id,
                    'checked_on' => $date->toDateString(),
                ]);
            }

            $checkin->fill($values);
            $checkin->save();

            return $checkin;
        });
    }
}
