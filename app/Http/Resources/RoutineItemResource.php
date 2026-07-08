<?php

namespace App\Http\Resources;

use App\Models\RoutineItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RoutineItem
 */
class RoutineItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category->value,
            'tracking_type' => $this->tracking_type->value,
            'default_load_unit' => $this->default_load_unit,
            'default_amount_unit' => $this->default_amount_unit,
            'note' => $this->note,
            'is_active' => $this->is_active,
            'life_area_id' => $this->life_area_id,
            'life_area' => LifeAreaResource::make($this->whenLoaded('lifeArea')),
            'videos' => $this->relationLoaded('videos')
                ? VideoResource::collection($this->videos)->resolve()
                : [],
            'videos_count' => $this->whenCounted('videos'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
