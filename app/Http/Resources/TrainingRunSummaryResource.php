<?php

namespace App\Http\Resources;

use App\Models\TrainingRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * プラン編集画面向けの軽量 run リソース（循環参照を避ける）。
 *
 * @mixin TrainingRun
 */
class TrainingRunSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'training_plan_id' => $this->training_plan_id,
            'status' => $this->status->value,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'note' => $this->note,
        ];
    }
}
