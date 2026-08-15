<?php

namespace App\Services;

use App\Enums\MetricValueType;
use App\Models\Metric;
use Illuminate\Database\Eloquent\Collection;

class EnsureMetricsService
{
    /**
     * 7 種類のメトリクスマスタを冪等に ensure する。
     *
     * key を一意キーに updateOrCreate するため、seed 漏れ・ラベル欠損があっても
     * 呼び出し時点で日本語ラベル付きのマスタへ復旧する。
     *
     * @return Collection<int, Metric> sort_order 順
     */
    public function handle(): Collection
    {
        foreach ($this->definitions() as $metric) {
            Metric::query()->updateOrCreate(
                ['key' => $metric['key']],
                $metric,
            );
        }

        return Metric::query()->orderBy('sort_order')->get();
    }

    /**
     * @return list<array{key: string, label: string, unit: string, value_type: MetricValueType, sort_order: int, is_advanced: bool}>
     */
    private function definitions(): array
    {
        return [
            [
                'key' => 'weight',
                'label' => '体重',
                'unit' => 'kg',
                'value_type' => MetricValueType::Decimal,
                'sort_order' => 1,
                'is_advanced' => false,
            ],
            [
                'key' => 'sleep_minutes',
                'label' => '睡眠時間',
                'unit' => '分',
                'value_type' => MetricValueType::Integer,
                'sort_order' => 2,
                'is_advanced' => false,
            ],
            [
                'key' => 'pitch_speed_max',
                'label' => '最高球速',
                'unit' => 'km/h',
                'value_type' => MetricValueType::Decimal,
                'sort_order' => 3,
                'is_advanced' => false,
            ],
            [
                'key' => 'pitch_count',
                'label' => '投球数',
                'unit' => '球',
                'value_type' => MetricValueType::Integer,
                'sort_order' => 4,
                'is_advanced' => false,
            ],
            [
                'key' => 'pain_level',
                'label' => '痛みレベル',
                'unit' => '1-5',
                'value_type' => MetricValueType::Scale15,
                'sort_order' => 5,
                'is_advanced' => false,
            ],
            [
                'key' => 'fatigue_level',
                'label' => '疲労レベル',
                'unit' => '1-5',
                'value_type' => MetricValueType::Scale15,
                'sort_order' => 6,
                'is_advanced' => false,
            ],
            [
                'key' => 'lean_body_mass',
                'label' => '徐脂肪体重',
                'unit' => 'kg',
                'value_type' => MetricValueType::Decimal,
                'sort_order' => 7,
                'is_advanced' => true,
            ],
        ];
    }
}
