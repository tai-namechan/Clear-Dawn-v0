<?php

namespace App\Http\Resources;

use App\Models\Routine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Routine
 */
class RoutineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'life_area_id' => $this->life_area_id,
            'steps_count' => $this->whenCounted('routineSteps'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
