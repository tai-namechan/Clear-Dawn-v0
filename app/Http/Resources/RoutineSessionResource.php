<?php

namespace App\Http\Resources;

use App\Models\RoutineSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RoutineSession
 */
class RoutineSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'routine_plan_id' => $this->routine_plan_id,
            'status' => $this->status->value,
            'started_at' => $this->started_at->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'note' => $this->note,
            'routine_plan' => RoutinePlanResource::make($this->whenLoaded('routinePlan')),
            'steps' => $this->relationLoaded('steps')
                ? RoutineSessionStepResource::collection($this->steps)->resolve()
                : [],
        ];
    }
}
