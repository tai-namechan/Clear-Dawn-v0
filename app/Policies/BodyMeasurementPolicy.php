<?php

namespace App\Policies;

use App\Models\BodyMeasurement;
use App\Models\User;

/**
 * 体組成測定の所有者認可。
 */
class BodyMeasurementPolicy
{
    /**
     * 対象測定の閲覧可否。
     */
    public function view(User $user, BodyMeasurement $bodyMeasurement): bool
    {
        return $this->owns($user, $bodyMeasurement);
    }

    /**
     * 新規取り込みの可否。
     */
    public function create(User $user): bool
    {
        return $user->exists;
    }

    /**
     * 対象測定の更新可否。
     */
    public function update(User $user, BodyMeasurement $bodyMeasurement): bool
    {
        return $this->owns($user, $bodyMeasurement);
    }

    private function owns(User $user, BodyMeasurement $bodyMeasurement): bool
    {
        return $bodyMeasurement->user_id === $user->id;
    }
}
