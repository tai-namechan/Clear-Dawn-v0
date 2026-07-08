<?php

namespace App\Http\Resources;

use App\Models\RoutineSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * プラン編集画面向けの軽量 session リソース（循環参照を避ける）。
 *
 * @mixin RoutineSession
 */
class RoutineSessionSummaryResource extends JsonResource
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
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'note' => $this->note,
        ];
    }
}
