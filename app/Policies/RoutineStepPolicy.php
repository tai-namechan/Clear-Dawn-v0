<?php

namespace App\Policies;

use App\Models\RoutineStep;
use App\Models\User;

class RoutineStepPolicy
{
    public function update(User $user, RoutineStep $step): bool
    {
        return $this->owns($user, $step);
    }

    public function delete(User $user, RoutineStep $step): bool
    {
        return $this->owns($user, $step);
    }

    private function owns(User $user, RoutineStep $step): bool
    {
        return $step->routine->user_id === $user->id;
    }
}
