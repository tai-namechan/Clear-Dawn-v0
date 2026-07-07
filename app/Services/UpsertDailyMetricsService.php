<?php

namespace App\Services;

use App\Models\Metric;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UpsertDailyMetricsService
{
    /**
     * 日次メトリクスを upsert する（unique: user_id + metric_id + recorded_on）。
     *
     * @param  list<array{
     *     metric_key: string,
     *     value: float|int|string,
     *     life_area_id?: string|null,
     *     note?: string|null
     * }>  $records
     */
    public function handle(User $user, Carbon $recordedOn, array $records): void
    {
        DB::transaction(function () use ($user, $recordedOn, $records): void {
            $metricsByKey = Metric::query()
                ->whereIn('key', array_column($records, 'metric_key'))
                ->get()
                ->keyBy('key');

            foreach ($records as $record) {
                /** @var Metric|null $metric */
                $metric = $metricsByKey->get($record['metric_key']);

                if ($metric === null) {
                    continue;
                }

                $user->metricRecords()->updateOrCreate(
                    [
                        'metric_id' => $metric->id,
                        'recorded_on' => $recordedOn->toDateString(),
                    ],
                    [
                        'value' => $record['value'],
                        'life_area_id' => $record['life_area_id'] ?? null,
                        'note' => $record['note'] ?? null,
                    ],
                );
            }
        });
    }
}
