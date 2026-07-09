<?php

namespace App\Support;

use App\Models\RoutineItem;
use App\Models\RoutinePlanStep;
use App\Models\RoutineStep;

/**
 * ステップ表示名・動画の解決ルールを一箇所に集約する。
 *
 * 表示名: step.title ?? item.name
 * 動画: step.video_id ?? item.default_video_id
 */
final class RoutineStepDisplay
{
    public static function resolveName(?string $title, ?RoutineItem $item): string
    {
        $trimmed = $title !== null ? trim($title) : '';

        if ($trimmed !== '') {
            return $trimmed;
        }

        return $item?->name ?? '';
    }

    public static function resolveVideoId(?string $stepVideoId, ?RoutineItem $item): ?string
    {
        if ($stepVideoId !== null && $stepVideoId !== '') {
            return $stepVideoId;
        }

        return $item?->default_video_id;
    }

    public static function fromRoutineStep(RoutineStep $step): array
    {
        $item = $step->relationLoaded('routineItem')
            ? $step->routineItem
            : $step->routineItem()->first();

        return [
            'title' => $step->title,
            'display_name' => self::resolveName($step->title, $item),
            'video_id' => self::resolveVideoId($step->video_id, $item),
        ];
    }

    public static function fromPlanStep(RoutinePlanStep $step): array
    {
        $item = $step->relationLoaded('routineItem')
            ? $step->routineItem
            : $step->routineItem()->first();

        return [
            'title' => $step->title,
            'display_name' => self::resolveName($step->title, $item),
            'video_id' => self::resolveVideoId($step->video_id, $item),
        ];
    }
}
