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
            'generation_source' => $this->generation_source,
            'program_version_id' => $this->program_version_id,
            'program_week_id' => $this->program_week_id,
            'program_day_template_id' => $this->program_day_template_id,
            'choice_option_id' => $this->choice_option_id,
            'adjustment_reason' => $this->adjustment_reason,
            'life_area' => LifeAreaResource::make($this->whenLoaded('lifeArea')),
            'steps' => $this->relationLoaded('steps')
                ? RoutinePlanStepResource::collection($this->steps)->resolve()
                : [],
            'sessions' => $this->relationLoaded('sessions')
                ? RoutineSessionSummaryResource::collection($this->sessions)->resolve()
                : [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
