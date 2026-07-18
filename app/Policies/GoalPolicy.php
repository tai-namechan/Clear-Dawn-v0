<?php

namespace App\Policies;

use App\Models\Goal;
use App\Models\User;

class GoalPolicy
{
    public function view(User $user, Goal $goal): bool
    {
        return $this->owns($user, $goal);
    }

    public function update(User $user, Goal $goal): bool
    {
        return $this->owns($user, $goal);
    }

    public function delete(User $user, Goal $goal): bool
    {
        return $this->owns($user, $goal);
    }

    private function owns(User $user, Goal $goal): bool
    {
        return $goal->user_id === $user->id;
    }
}
