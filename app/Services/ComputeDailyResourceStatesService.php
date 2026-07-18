<?php

namespace App\Services;

use App\Models\DailyCheckin;
use App\Models\DailyResourceState;
use App\Models\PersonalBaseline;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * チェックイン値から簡易 EWMA / readiness を日次計算する（決定論・同入力同出力）。
 * 較正期間（baseline sample_count < 28）は readiness を null のまま残す。
 *
 * ベースライン（mean / stddev / sample_count）は当日までのチェックイン履歴から
 * 毎回再集計するため、同日チェックインの再送信でも結果は冪等。
 */
class ComputeDailyResourceStatesService
{
    private const EWMA_ALPHA = 0.3;

    private const CALIBRATION_SAMPLES = 28;

    private const MIN_STDDEV = 0.5;

    /** @var list<string> */
    private const RESOURCES = [
        'sleep_quality',
        'fatigue',
        'muscle_soreness',
        'stress',
        'mood',
        'readiness_self',
    ];

    /**
     * @return Collection<int, DailyResourceState>
     */
    public function handle(User $user, Carbon $date): Collection
    {
        $checkin = DailyCheckin::query()
            ->where('user_id', $user->id)
            ->whereDate('checked_on', $date->toDateString())
            ->first();

        if ($checkin === null) {
            return collect();
        }

        $history = DailyCheckin::query()
            ->where('user_id', $user->id)
            ->whereDate('checked_on', '<=', $date->toDateString())
            ->orderBy('checked_on')
            ->get();

        return DB::transaction(function () use ($user, $date, $checkin, $history): Collection {
            $states = collect();

            foreach (self::RESOURCES as $resourceKey) {
                $value = $checkin->{$resourceKey};

                if ($value === null) {
                    continue;
                }

                $previous = DailyResourceState::query()
                    ->where('user_id', $user->id)
                    ->where('resource_key', $resourceKey)
                    ->whereDate('state_on', '<', $date->toDateString())
                    ->orderByDesc('state_on')
                    ->first();

                $ewma = $previous?->ewma !== null
                    ? (self::EWMA_ALPHA * (float) $value) + ((1 - self::EWMA_ALPHA) * (float) $previous->ewma)
                    : (float) $value;

                /** @var Collection<int, DailyCheckin> $samples */
                $samples = $history->filter(
                    fn (DailyCheckin $entry): bool => $entry->{$resourceKey} !== null,
                );
                $values = $samples
                    ->map(fn (DailyCheckin $entry): float => (float) $entry->{$resourceKey})
                    ->values();

                $sampleCount = $values->count();
                $mean = $sampleCount > 0 ? $values->sum() / $sampleCount : (float) $value;
                $variance = $sampleCount > 0
                    ? $values->sum(fn (float $sample): float => ($sample - $mean) ** 2) / $sampleCount
                    : 0.0;
                $stddev = sqrt($variance);

                $baseline = PersonalBaseline::query()->firstOrNew([
                    'user_id' => $user->id,
                    'resource_key' => $resourceKey,
                ]);
                $baseline->mean_value = round($mean, 4);
                $baseline->stddev_value = round($stddev, 4);
                $baseline->sample_count = $sampleCount;
                $baseline->window_start = $samples->first()?->checked_on?->toDateString() ?? $date->toDateString();
                $baseline->window_end = $date->toDateString();
                $baseline->computed_at = now();
                $baseline->save();

                $zLoad = null;
                $readiness = null;

                if ($sampleCount >= self::CALIBRATION_SAMPLES) {
                    $effectiveStddev = max(self::MIN_STDDEV, $stddev);
                    $zLoad = ((float) $value - $mean) / $effectiveStddev;
                    $readiness = match ($resourceKey) {
                        'fatigue', 'muscle_soreness', 'stress' => max(0, min(10, 5 - $zLoad)),
                        default => max(0, min(10, 5 + $zLoad)),
                    };
                }

                $state = DailyResourceState::query()
                    ->where('user_id', $user->id)
                    ->where('resource_key', $resourceKey)
                    ->whereDate('state_on', $date->toDateString())
                    ->first();

                if ($state === null) {
                    $state = new DailyResourceState([
                        'user_id' => $user->id,
                        'state_on' => $date->toDateString(),
                        'resource_key' => $resourceKey,
                    ]);
                }

                $state->fill([
                    'ewma' => round($ewma, 4),
                    'z_load' => $zLoad !== null ? round($zLoad, 4) : null,
                    'rel_strain' => $zLoad !== null ? round(abs($zLoad), 4) : null,
                    'readiness' => $readiness !== null ? round($readiness, 4) : null,
                    'inputs_snapshot' => [
                        'raw' => $value,
                        'sample_count' => $sampleCount,
                        'calibrating' => $sampleCount < self::CALIBRATION_SAMPLES,
                    ],
                ]);
                $state->save();

                $states->push($state);
            }

            return $states->values();
        });
    }
}
