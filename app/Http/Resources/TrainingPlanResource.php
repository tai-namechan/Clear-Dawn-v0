<?php

namespace App\Http\Resources;

use App\Models\TrainingPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TrainingPlan
 */
class TrainingPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'scheduled_on' => $this->scheduled_on->toDateString(),
            'status' => $this->status->value,
            'note' => $this->note,
            'life_area_id' => $this->life_area_id,
            'routine_id' => $this->routine_id,
            'life_area' => LifeAreaResource::make($this->whenLoaded('lifeArea')),
            'steps' => TrainingPlanStepResource::collection($this->whenLoaded('steps')),
            'runs' => TrainingRunResource::collection($this->whenLoaded('runs')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
