<?php

namespace App\Services;

use App\Models\MealEntry;

class DeleteMealEntryService
{
    public function __construct(
        private readonly MealPhotoService $photos,
    ) {}

    public function handle(MealEntry $mealEntry): void
    {
        $mealEntry->delete();
        $this->photos->deleteFor($mealEntry);
    }
}
