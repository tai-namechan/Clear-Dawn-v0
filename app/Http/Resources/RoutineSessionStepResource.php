<?php

namespace App\Http\Resources;

use App\Models\RoutineSessionStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RoutineSessionStep
 */
class RoutineSessionStepResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'routine_session_id' => $this->routine_session_id,
            'routine_item_id' => $this->routine_item_id,
            'item_name' => $this->item_name,
            'video_id' => $this->video_id,
            'purpose' => $this->purpose?->value,
            'sort_order' => $this->sort_order,
            'target_load' => $this->target_load !== null ? (string) $this->target_load : null,
            'load_unit' => $this->load_unit,
            'target_amount' => $this->target_amount !== null ? (string) $this->target_amount : null,
            'amount_unit' => $this->amount_unit,
            'target_blocks' => $this->target_blocks,
            'rest_seconds' => $this->rest_seconds,
            'status' => $this->status?->value,
            'actual_duration_seconds' => $this->actual_duration_seconds,
            'memo' => $this->memo,
            'routine_item' => $this->whenLoaded(
                'routineItem',
                fn () => $this->routineItem
                    ? RoutineItemResource::make($this->routineItem)->resolve()
                    : null,
            ),
            'video' => $this->whenLoaded(
                'video',
                fn () => $this->video
                    ? VideoResource::make($this->video)->resolve()
                    : null,
            ),
            'block_logs' => $this->relationLoaded('blockLogs')
                ? RoutineBlockLogResource::collection($this->blockLogs)->resolve()
                : [],
        ];
    }
}
