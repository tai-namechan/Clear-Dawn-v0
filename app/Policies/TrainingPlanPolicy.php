<?php

namespace App\Policies;

use App\Enums\TrainingPlanStatus;
use App\Models\TrainingPlan;
use App\Models\User;

class TrainingPlanPolicy
{
    public function view(User $user, TrainingPlan $plan): bool
    {
        return $this->owns($user, $plan);
    }

    public function update(User $user, TrainingPlan $plan): bool
    {
        return $this->owns($user, $plan);
    }

    /**
     * ステップの追加・更新・削除・並び替えは draft プランのみ。
     */
    public function updateSteps(User $user, TrainingPlan $plan): bool
    {
        return $this->owns($user, $plan) && $plan->status === TrainingPlanStatus::Draft;
    }

    /**
     * 実行履歴がないプランのみ削除可能。
     */
    public function delete(User $user, TrainingPlan $plan): bool
    {
        return $this->owns($user, $plan) && ! $plan->runs()->exists();
    }

    public function start(User $user, TrainingPlan $plan): bool
    {
        return $this->owns($user, $plan)
            && $plan->status === TrainingPlanStatus::Ready
            && $plan->steps()->exists();
    }

    private function owns(User $user, TrainingPlan $plan): bool
    {
        return $plan->user_id === $user->id;
    }
}
