<?php

namespace App\Queries;

use App\Enums\BodyMeasurementStatus;
use App\Models\BodyMeasurement;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * 指定日の確定体組成測定の取得。
 */
class GetConfirmedBodyMeasurementQuery
{
    /**
     * 指定ユーザー・日付の確定測定を返す。
     */
    public function handle(User $user, Carbon $date): ?BodyMeasurement
    {
        return BodyMeasurement::query()
            ->whereBelongsTo($user)
            ->whereDate('measured_on', $date->toDateString())
            ->where('parse_status', BodyMeasurementStatus::Confirmed)
            ->with('segments')
            ->first();
    }
}
