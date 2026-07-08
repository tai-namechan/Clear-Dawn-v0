<?php

namespace App\Policies;

use App\Models\RoutineItem;
use App\Models\User;

class RoutineItemPolicy
{
    public function view(User $user, RoutineItem $routineItem): bool
    {
        return $this->owns($user, $routineItem);
    }

    public function update(User $user, RoutineItem $routineItem): bool
    {
        return $this->owns($user, $routineItem);
    }

    public function delete(User $user, RoutineItem $routineItem): bool
    {
        return $this->owns($user, $routineItem);
    }

    private function owns(User $user, RoutineItem $routineItem): bool
    {
        return $routineItem->user_id === $user->id;
    }
}
