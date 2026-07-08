<?php

namespace Database\Seeders;

use App\Enums\MetricValueType;
use App\Models\Metric;
use Illuminate\Database\Seeder;

class MetricSeeder extends Seeder
{
    /**
     * 6 種類のメトリクスマスタを冪等に投入する。
     */
    public function run(): void
    {
        $metrics = [
            [
                'key' => 'weight',
                'label' => '体重',
                'unit' => 'kg',
                'value_type' => MetricValueType::Decimal,
                'sort_order' => 1,
            ],
            [
                'key' => 'sleep_minutes',
                'label' => '睡眠時間',
                'unit' => '分',
                'value_type' => MetricValueType::Integer,
                'sort_order' => 2,
            ],
            [
                'key' => 'pitch_speed_max',
                'label' => '最高球速',
                'unit' => 'km/h',
                'value_type' => MetricValueType::Decimal,
                'sort_order' => 3,
            ],
            [
                'key' => 'pitch_count',
                'label' => '投球数',
                'unit' => '球',
                'value_type' => MetricValueType::Integer,
                'sort_order' => 4,
            ],
            [
                'key' => 'pain_level',
                'label' => '痛みレベル',
                'unit' => '1-5',
                'value_type' => MetricValueType::Scale15,
                'sort_order' => 5,
            ],
            [
                'key' => 'fatigue_level',
                'label' => '疲労度',
                'unit' => '1-5',
                'value_type' => MetricValueType::Scale15,
                'sort_order' => 6,
            ],
        ];

        foreach ($metrics as $metric) {
            Metric::query()->updateOrCreate(
                ['key' => $metric['key']],
                $metric,
            );
        }
    }
}
