<?php

namespace App\Policies;

use App\Models\FoodItem;
use App\Models\User;

class FoodItemPolicy
{
    public function view(User $user, FoodItem $foodItem): bool
    {
        return $this->owns($user, $foodItem);
    }

    public function update(User $user, FoodItem $foodItem): bool
    {
        return $this->owns($user, $foodItem);
    }

    public function delete(User $user, FoodItem $foodItem): bool
    {
        return $this->owns($user, $foodItem);
    }

    private function owns(User $user, FoodItem $foodItem): bool
    {
        return $foodItem->user_id === $user->id;
    }
}
