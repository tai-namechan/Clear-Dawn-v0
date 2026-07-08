<?php

namespace App\Policies;

use App\Models\Routine;
use App\Models\User;

class RoutinePolicy
{
    public function view(User $user, Routine $routine): bool
    {
        return $this->owns($user, $routine);
    }

    public function update(User $user, Routine $routine): bool
    {
        return $this->owns($user, $routine);
    }

    public function delete(User $user, Routine $routine): bool
    {
        return $this->owns($user, $routine);
    }

    private function owns(User $user, Routine $routine): bool
    {
        return $routine->user_id === $user->id;
    }
}
