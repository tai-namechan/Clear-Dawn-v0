<?php

namespace App\Queries;

use App\Enums\BodyMeasurementStatus;
use App\Models\BodyMeasurement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
        return $this->baseQuery($user, $date)
            ->where('parse_status', BodyMeasurementStatus::Confirmed)
            ->first();
    }

    /**
     * 指定日の最新測定。要確認も含む。
     *
     * Body Story には使わず、取り込み結果の表示だけに使う。
     */
    public function handleLatest(User $user, Carbon $date): ?BodyMeasurement
    {
        return $this->baseQuery($user, $date)->first();
    }

    /**
     * @return Builder<BodyMeasurement>
     */
    private function baseQuery(User $user, Carbon $date): Builder
    {
        return BodyMeasurement::query()
            ->whereBelongsTo($user)
            ->whereDate('measured_on', $date->toDateString())
            ->with('segments');
    }
}
