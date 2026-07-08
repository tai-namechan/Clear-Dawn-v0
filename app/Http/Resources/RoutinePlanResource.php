<?php

namespace App\Http\Resources;

use App\Models\RoutinePlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RoutinePlan
 */
class RoutinePlanResource extends JsonResource
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
            'steps' => $this->relationLoaded('steps')
                ? RoutinePlanStepResource::collection($this->steps)->resolve()
                : [],
            'sessions' => $this->relationLoaded('sessions')
                ? RoutineSessionResource::collection($this->sessions)->resolve()
                : [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
