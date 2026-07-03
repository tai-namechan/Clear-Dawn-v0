<?php

namespace App\Services;

use App\Enums\ActivityLogEventType;
use App\Models\ActivityLog;
use App\Models\MatrixCellItem;
use Illuminate\Support\Facades\DB;

class ToggleMatrixCellItemCompletionService
{
    /**
     * 完了状態を切り替え、activity_logs に不変イベントを記録する。
     *
     * - 未完了 → 完了: completed_at = now()、matrix_item_completed を記録
     * - 完了 → 再開: completed_at = null、matrix_item_reopened を記録
     *
     * 行が checkable か・所有者かの判定は Policy（MatrixCellItemPolicy@toggle）で行う。
     */
    public function handle(MatrixCellItem $item): MatrixCellItem
    {
        return DB::transaction(function () use ($item): MatrixCellItem {
            $completing = ! $item->is_completed;
            $occurredAt = now();

            $item->update([
                'is_completed' => $completing,
                'completed_at' => $completing ? $occurredAt : null,
            ]);

            ActivityLog::query()->create([
                'user_id' => $item->matrixCell->user_id,
                'event_type' => $completing
                    ? ActivityLogEventType::MatrixItemCompleted
                    : ActivityLogEventType::MatrixItemReopened,
                'subject_type' => $item->getMorphClass(),
                'subject_id' => $item->id,
                'occurred_at' => $occurredAt,
            ]);

            return $item;
        });
    }
}
