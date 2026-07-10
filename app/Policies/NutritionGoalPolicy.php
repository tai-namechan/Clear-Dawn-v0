<?php

namespace App\Policies;

use App\Models\NutritionGoal;
use App\Models\User;

class NutritionGoalPolicy
{
    public function view(User $user, NutritionGoal $nutritionGoal): bool
    {
        return $this->owns($user, $nutritionGoal);
    }

    public function update(User $user, NutritionGoal $nutritionGoal): bool
    {
        return $this->owns($user, $nutritionGoal);
    }

    private function owns(User $user, NutritionGoal $nutritionGoal): bool
    {
        return $nutritionGoal->user_id === $user->id;
    }
}
