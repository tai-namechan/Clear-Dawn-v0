<?php

namespace Database\Factories;

use App\Models\Metric;
use App\Models\ProgramMetricTarget;
use App\Models\ProgramVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgramMetricTarget>
 */
class ProgramMetricTargetFactory extends Factory
{
    protected $model = ProgramMetricTarget::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_version_id' => ProgramVersion::factory(),
            'metric_id' => fn () => Metric::query()->firstOrCreate(
                ['key' => 'test_metric_'.fake()->unique()->numberBetween(1, 100000)],
                ['label' => 'テスト指標', 'unit' => 'kg', 'value_type' => 'decimal', 'sort_order' => 999],
            )->id,
            'target_value' => fake()->randomFloat(1, 60, 200),
        ];
    }
}
