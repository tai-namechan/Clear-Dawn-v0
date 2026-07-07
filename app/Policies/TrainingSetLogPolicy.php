<?php

namespace App\Policies;

use App\Models\TrainingRun;
use App\Models\TrainingSetLog;
use App\Models\User;

class TrainingSetLogPolicy
{
    public function update(User $user, TrainingSetLog $setLog): bool
    {
        return $this->canModify($user, $setLog);
    }

    public function delete(User $user, TrainingSetLog $setLog): bool
    {
        return $this->canModify($user, $setLog);
    }

    private function canModify(User $user, TrainingSetLog $setLog): bool
    {
        /** @var TrainingRun $run */
        $run = $setLog->trainingRunStep->trainingRun;

        return $run->user_id === $user->id;
    }
}
