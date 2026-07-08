<?php

namespace App\Policies;

use App\Enums\RoutinePlanStatus;
use App\Models\RoutinePlan;
use App\Models\User;

class RoutinePlanPolicy
{
    public function view(User $user, RoutinePlan $plan): bool
    {
        return $this->owns($user, $plan);
    }

    public function update(User $user, RoutinePlan $plan): bool
    {
        return $this->owns($user, $plan);
    }

    /**
     * ステップの追加・更新・削除・並び替えは draft プランのみ。
     */
    public function updateSteps(User $user, RoutinePlan $plan): bool
    {
        return $this->owns($user, $plan) && $plan->status === RoutinePlanStatus::Draft;
    }

    /**
     * 実行履歴がないプランのみ削除可能。
     */
    public function delete(User $user, RoutinePlan $plan): bool
    {
        return $this->owns($user, $plan) && ! $plan->sessions()->exists();
    }

    public function start(User $user, RoutinePlan $plan): bool
    {
        return $this->owns($user, $plan)
            && $plan->status === RoutinePlanStatus::Ready
            && $plan->steps()->exists();
    }

    private function owns(User $user, RoutinePlan $plan): bool
    {
        return $plan->user_id === $user->id;
    }
}
