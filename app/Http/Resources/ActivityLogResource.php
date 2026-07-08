<?php

namespace App\Http\Resources;

use App\Enums\ActivityLogEventType;
use App\Models\ActivityLog;
use App\Models\MatrixCellItem;
use App\Models\TrainingRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ActivityLog
 */
class ActivityLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type->value,
            'occurred_at' => $this->occurred_at->toIso8601String(),
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'subject_summary' => $this->resolveSubjectSummary(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveSubjectSummary(): ?array
    {
        $subject = $this->subject;

        if ($subject instanceof MatrixCellItem) {
            return [
                'type' => 'matrix_cell_item',
                'title' => $subject->title,
            ];
        }

        if ($subject instanceof TrainingRun) {
            return [
                'type' => 'training_run',
                'plan_title' => $subject->trainingPlan?->title,
                'status' => $subject->status->value,
            ];
        }

        if ($this->event_type === ActivityLogEventType::TrainingRunCompleted) {
            return [
                'type' => 'training_run',
            ];
        }

        return null;
    }
}
