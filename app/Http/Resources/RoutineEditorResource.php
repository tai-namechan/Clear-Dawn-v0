<?php

namespace App\Http\Resources;

use App\Models\Routine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Routine
 */
class RoutineEditorResource extends JsonResource
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
            'life_area' => LifeAreaResource::make($this->whenLoaded('lifeArea')),
            'steps' => RoutineStepResource::collection($this->whenLoaded('routineSteps')),
        ];
    }
}
