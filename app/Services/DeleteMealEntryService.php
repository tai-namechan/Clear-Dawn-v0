<?php

namespace App\Services;

use App\Models\MealEntry;

class DeleteMealEntryService
{
    public function handle(MealEntry $mealEntry): void
    {
        $mealEntry->delete();
    }
}
