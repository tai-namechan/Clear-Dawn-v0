<?php

namespace App\Http\Resources;

use App\Models\RoutinePlanStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RoutinePlanStep
 */
class RoutinePlanStepResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'routine_plan_id' => $this->routine_plan_id,
            'routine_item_id' => $this->routine_item_id,
            'video_id' => $this->video_id,
            'purpose' => $this->purpose?->value,
            'sort_order' => $this->sort_order,
            'target_load' => $this->target_load !== null ? (string) $this->target_load : null,
            'load_unit' => $this->load_unit,
            'target_amount' => $this->target_amount !== null ? (string) $this->target_amount : null,
            'amount_unit' => $this->amount_unit,
            'target_blocks' => $this->target_blocks,
            'rest_seconds' => $this->rest_seconds,
            'note' => $this->note,
            'routine_item' => RoutineItemResource::make($this->whenLoaded('routineItem')),
            'video' => VideoResource::make($this->whenLoaded('video')),
        ];
    }
}
