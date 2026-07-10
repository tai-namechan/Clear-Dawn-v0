<?php

namespace Database\Factories\Domain\Shared;

use App\Domain\Shared\Models\AiUsageLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiUsageLog>
 */
class AiUsageLogFactory extends Factory
{
    protected $model = AiUsageLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'feature' => 'kioku.classify',
            'model' => 'claude-haiku-4-5-20251001',
            'input_tokens' => 100,
            'output_tokens' => 50,
            'estimated_cost_usd' => 0.0010,
            'created_at' => now(),
        ];
    }
}
