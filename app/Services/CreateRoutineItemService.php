<?php

namespace App\Services;

use App\Enums\RoutineItemCategory;
use App\Enums\TrackingType;
use App\Models\RoutineItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateRoutineItemService
{
    /**
     * @param  array{
     *     name: string,
     *     life_area_id?: string|null,
     *     category: RoutineItemCategory,
     *     tracking_type: TrackingType,
     *     default_load_unit?: string|null,
     *     default_amount_unit?: string|null,
     *     default_video_id?: string|null,
     *     note?: string|null,
     *     is_active?: bool
     * }  $attributes
     */
    public function handle(User $user, array $attributes): RoutineItem
    {
        return DB::transaction(function () use ($user, $attributes): RoutineItem {
            return $user->routineItems()->create([
                'name' => $attributes['name'],
                'life_area_id' => $attributes['life_area_id'] ?? null,
                'category' => $attributes['category'],
                'tracking_type' => $attributes['tracking_type'],
                'default_load_unit' => $attributes['default_load_unit'] ?? null,
                'default_amount_unit' => $attributes['default_amount_unit'] ?? null,
                'default_video_id' => $attributes['default_video_id'] ?? null,
                'note' => $attributes['note'] ?? null,
                'is_active' => $attributes['is_active'] ?? true,
            ]);
        });
    }
}
