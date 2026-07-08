<?php

namespace App\Http\Resources;

use App\Models\TrainingSetLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TrainingSetLog
 */
class TrainingSetLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'training_run_step_id' => $this->training_run_step_id,
            'set_number' => $this->set_number,
            'weight_kg' => $this->weight_kg !== null ? (string) $this->weight_kg : null,
            'reps' => $this->reps,
            'distance_m' => $this->distance_m !== null ? (string) $this->distance_m : null,
            'duration_seconds' => $this->duration_seconds,
            'memo' => $this->memo,
        ];
    }
}
