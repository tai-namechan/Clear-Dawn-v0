<?php

namespace App\Http\Resources;

use App\Models\TrainingRunStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TrainingRunStep
 */
class TrainingRunStepResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'training_run_id' => $this->training_run_id,
            'exercise_id' => $this->exercise_id,
            'exercise_name' => $this->exercise_name,
            'video_id' => $this->video_id,
            'purpose' => $this->purpose?->value,
            'sort_order' => $this->sort_order,
            'target_sets' => $this->target_sets,
            'target_reps' => $this->target_reps,
            'target_weight_kg' => $this->target_weight_kg !== null ? (string) $this->target_weight_kg : null,
            'target_distance_m' => $this->target_distance_m !== null ? (string) $this->target_distance_m : null,
            'target_duration_seconds' => $this->target_duration_seconds,
            'rest_seconds' => $this->rest_seconds,
            'status' => $this->status->value,
            'actual_duration_seconds' => $this->actual_duration_seconds,
            'memo' => $this->memo,
            'exercise' => ExerciseResource::make($this->whenLoaded('exercise')),
            'video' => VideoResource::make($this->whenLoaded('video')),
            'set_logs' => TrainingSetLogResource::collection($this->whenLoaded('setLogs')),
        ];
    }
}
