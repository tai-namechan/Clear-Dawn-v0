<?php

namespace App\Policies;

use App\Models\MealEntry;
use App\Models\User;

class MealEntryPolicy
{
    public function view(User $user, MealEntry $mealEntry): bool
    {
        return $this->owns($user, $mealEntry);
    }

    public function update(User $user, MealEntry $mealEntry): bool
    {
        return $this->owns($user, $mealEntry);
    }

    public function delete(User $user, MealEntry $mealEntry): bool
    {
        return $this->owns($user, $mealEntry);
    }

    private function owns(User $user, MealEntry $mealEntry): bool
    {
        return $mealEntry->user_id === $user->id;
    }
}
