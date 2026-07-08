<?php

namespace App\Policies;

use App\Models\Exercise;
use App\Models\User;

class ExercisePolicy
{
    public function view(User $user, Exercise $exercise): bool
    {
        return $this->owns($user, $exercise);
    }

    public function update(User $user, Exercise $exercise): bool
    {
        return $this->owns($user, $exercise);
    }

    public function delete(User $user, Exercise $exercise): bool
    {
        return $this->owns($user, $exercise);
    }

    private function owns(User $user, Exercise $exercise): bool
    {
        return $exercise->user_id === $user->id;
    }
}
