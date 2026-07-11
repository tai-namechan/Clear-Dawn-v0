<?php

namespace Database\Factories\Domain\Shared;

use App\Domain\Shared\Models\AiUsageMonthly;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiUsageMonthly>
 */
class AiUsageMonthlyFactory extends Factory
{
    protected $model = AiUsageMonthly::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'period' => now()->format('Y-m'),
            'spent_usd' => '0.000000',
            'reserved_usd' => '0.000000',
        ];
    }
}
