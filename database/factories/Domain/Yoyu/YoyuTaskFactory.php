<?php

namespace Database\Factories\Domain\Yoyu;

use App\Domain\Yoyu\Models\YoyuTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<YoyuTask>
 */
class YoyuTaskFactory extends Factory
{
    protected $model = YoyuTask::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'status' => 'planned',
            'estimate_minutes' => 30,
        ];
    }
}
