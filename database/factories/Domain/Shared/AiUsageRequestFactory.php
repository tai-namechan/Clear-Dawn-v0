<?php

namespace Database\Factories\Domain\Shared;

use App\Domain\Shared\Models\AiUsageRequest;
use App\Enums\AiUsageRequestStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiUsageRequest>
 */
class AiUsageRequestFactory extends Factory
{
    protected $model = AiUsageRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'period' => now()->format('Y-m'),
            'feature' => 'kioku.classify',
            'model' => 'claude-haiku-4-5-20251001',
            'estimated_usd' => '0.010000',
            'actual_usd' => null,
            'charged_usd' => null,
            'status' => AiUsageRequestStatus::Reserved,
            'provider_started_at' => null,
            'finished_at' => null,
            'failure_code' => null,
        ];
    }
}
