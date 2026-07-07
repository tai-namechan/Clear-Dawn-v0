<?php

namespace App\Http\Resources;

use App\Models\Exercise;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Exercise
 */
class ExerciseResource extends JsonResource
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
            'note' => $this->note,
            'is_active' => $this->is_active,
            'life_area_id' => $this->life_area_id,
            'videos_count' => $this->whenCounted('videos'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
