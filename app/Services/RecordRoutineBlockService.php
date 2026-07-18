<?php

namespace App\Services;

use App\Models\RoutineBlockLog;
use App\Models\RoutineSessionStep;
use Illuminate\Support\Facades\DB;

class RecordRoutineBlockService
{
    /**
     * block_number はサーバー側で採番する。
     *
     * @param  array{
     *     load_value?: float|string|null,
     *     load_unit?: string|null,
     *     amount_value?: float|string|null,
     *     amount_unit?: string|null,
     *     memo?: string|null,
     *     rpe?: float|string|null,
     *     distance_value?: float|string|null,
     *     duration_seconds?: int|null,
     *     side?: string|null,
     *     extra?: array<string, mixed>|null
     * }  $attributes
     */
    public function handle(RoutineSessionStep $sessionStep, array $attributes): RoutineBlockLog
    {
        return DB::transaction(function () use ($sessionStep, $attributes): RoutineBlockLog {
            $nextBlockNumber = (int) $sessionStep->blockLogs()->max('block_number') + 1;

            return $sessionStep->blockLogs()->create([
                'block_number' => $nextBlockNumber,
                'load_value' => $attributes['load_value'] ?? null,
                'load_unit' => $attributes['load_unit'] ?? null,
                'amount_value' => $attributes['amount_value'] ?? null,
                'amount_unit' => $attributes['amount_unit'] ?? null,
                'memo' => $attributes['memo'] ?? null,
                'rpe' => $attributes['rpe'] ?? null,
                'distance_value' => $attributes['distance_value'] ?? null,
                'duration_seconds' => $attributes['duration_seconds'] ?? null,
                'side' => $attributes['side'] ?? null,
                'extra' => $attributes['extra'] ?? null,
            ]);
        });
    }
}
