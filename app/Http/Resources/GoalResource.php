<?php

namespace App\Http\Resources;

use App\Models\Goal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Goal
 */
class GoalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_goal_id' => $this->parent_goal_id,
            'matrix_cell_id' => $this->matrix_cell_id,
            'name' => $this->name,
            'why' => $this->why,
            'priority' => $this->priority,
            'status' => $this->status->value,
            'deadline' => $this->deadline?->toDateString(),
            'sort_order' => $this->sort_order,
            'parent' => $this->whenLoaded('parent', fn () => $this->parent === null ? null : [
                'id' => $this->parent->id,
                'name' => $this->parent->name,
            ]),
            'matrix_cell' => $this->whenLoaded('matrixCell', fn () => $this->matrixCell === null ? null : [
                'id' => $this->matrixCell->id,
                'life_area' => $this->matrixCell->lifeArea?->name,
            ]),
            'goal_metrics' => $this->when(
                $this->relationLoaded('goalMetrics'),
                fn () => GoalMetricResource::collection($this->goalMetrics)->resolve(),
            ),
            'children' => $this->when(
                $this->relationLoaded('children'),
                fn () => self::collection($this->children)->resolve(),
            ),
            'programs' => $this->whenLoaded('programs', fn () => $this->programs->map(fn ($program) => [
                'id' => $program->id,
                'name' => $program->name,
                'status' => $program->status->value,
            ])->all()),
            'change_logs' => $this->whenLoaded('changeLogs', fn () => $this->changeLogs->map(fn ($log) => [
                'id' => $log->id,
                'changes' => $log->changes,
                'reason' => $log->reason,
                'created_at' => $log->created_at->toIso8601String(),
            ])->all()),
        ];
    }
}
