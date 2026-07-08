<?php

namespace App\Services;

use App\Enums\ExerciseCategory;
use App\Enums\TrackingType;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateExerciseService
{
    /**
     * @param  array{
     *     name: string,
     *     life_area_id?: string|null,
     *     category: ExerciseCategory,
     *     tracking_type: TrackingType,
     *     note?: string|null,
     *     is_active?: bool
     * }  $attributes
     */
    public function handle(User $user, array $attributes): Exercise
    {
        return DB::transaction(function () use ($user, $attributes): Exercise {
            return $user->exercises()->create([
                'name' => $attributes['name'],
                'life_area_id' => $attributes['life_area_id'] ?? null,
                'category' => $attributes['category'],
                'tracking_type' => $attributes['tracking_type'],
                'note' => $attributes['note'] ?? null,
                'is_active' => $attributes['is_active'] ?? true,
            ]);
        });
    }
}
