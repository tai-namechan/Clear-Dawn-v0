<?php

namespace App\Services;

use App\Enums\PlanGenerationSource;
use App\Enums\RoutinePlanStatus;
use App\Models\RoutinePlan;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 承認段 A（今日だけ）: プログラム由来プランの当日スナップショットを理由つきで調整する。
 * プログラム本体は変更しない。
 */
class ApplyTodayPlanAdjustmentService
{
    /**
     * @param  array{
     *     action: 'adjust'|'skip'|'lighten'|'execute',
     *     reason: string,
     *     note?: string|null
     * }  $attributes
     */
    public function handle(RoutinePlan $plan, array $attributes): RoutinePlan
    {
        if ($plan->generation_source !== PlanGenerationSource::Program->value) {
            throw new InvalidArgumentException('承認Aはプログラム由来プランのみ対象です。');
        }

        if ($plan->sessions()->where('status', 'completed')->exists()) {
            throw new InvalidArgumentException('完了済みセッションがあるプランは当日調整できません。');
        }

        return DB::transaction(function () use ($plan, $attributes): RoutinePlan {
            $action = $attributes['action'];
            $reason = $attributes['reason'];

            return match ($action) {
                'execute' => tap($plan, function (RoutinePlan $plan) use ($reason): void {
                    $plan->update([
                        'adjustment_reason' => $reason,
                        'status' => RoutinePlanStatus::Ready,
                    ]);
                }),
                'adjust' => tap($plan, function (RoutinePlan $plan) use ($reason, $attributes): void {
                    $plan->update([
                        'adjustment_reason' => $reason,
                        'status' => RoutinePlanStatus::Draft,
                        'note' => $attributes['note'] ?? $plan->note,
                    ]);
                }),
                'lighten' => tap($plan, function (RoutinePlan $plan) use ($reason, $attributes): void {
                    $plan->steps()
                        ->where('required_level', 'skippable')
                        ->delete();

                    $plan->update([
                        'adjustment_reason' => $reason,
                        'status' => RoutinePlanStatus::Ready,
                        'note' => $attributes['note'] ?? '軽負荷版（skippable STEP を除外）',
                    ]);
                }),
                'skip' => tap($plan, function (RoutinePlan $plan) use ($reason, $attributes): void {
                    $plan->update([
                        'adjustment_reason' => $reason,
                        'status' => RoutinePlanStatus::Archived,
                        'note' => $attributes['note'] ?? '見送り',
                    ]);
                }),
                default => throw new InvalidArgumentException('未知の承認Aアクションです。'),
            };
        });
    }
}
