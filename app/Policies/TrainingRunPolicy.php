<?php

namespace App\Policies;

use App\Enums\TrainingPlanStatus;
use App\Enums\TrainingRunStatus;
use App\Models\TrainingPlan;
use App\Models\TrainingRun;
use App\Models\User;

class TrainingRunPolicy
{
    public function view(User $user, TrainingRun $run): bool
    {
        return $this->owns($user, $run);
    }

    public function start(User $user, TrainingPlan $plan): bool
    {
        return $plan->user_id === $user->id
            && $plan->status === TrainingPlanStatus::Ready
            && $plan->steps()->exists();
    }

    public function record(User $user, TrainingRun $run): bool
    {
        return $this->owns($user, $run) && $run->status === TrainingRunStatus::InProgress;
    }

    public function complete(User $user, TrainingRun $run): bool
    {
        return $this->owns($user, $run) && in_array($run->status, [
            TrainingRunStatus::InProgress,
            TrainingRunStatus::Completed,
        ], true);
    }

    public function abort(User $user, TrainingRun $run): bool
    {
        return $this->owns($user, $run) && $run->status === TrainingRunStatus::InProgress;
    }

    private function owns(User $user, TrainingRun $run): bool
    {
        return $run->user_id === $user->id;
    }
}
