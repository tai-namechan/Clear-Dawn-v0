<?php

namespace Database\Factories\Domain\Kioku;

use App\Domain\Kioku\Models\Memory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Memory>
 */
class MemoryFactory extends Factory
{
    protected $model = Memory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'source_type' => 'manual',
            'memory_type' => 'thought',
            'title' => fake()->sentence(3),
            'raw_content' => fake()->paragraph(),
            'summary' => fake()->sentence(),
            'structured_data' => null,
            'tags' => ['sample'],
            'captured_at' => now(),
            'importance' => 3,
            'sensitive' => false,
            'status' => 'ready',
            'referenced_count' => 0,
        ];
    }

    public function captured(): static
    {
        return $this->state(fn () => [
            'memory_type' => null,
            'summary' => null,
            'structured_data' => null,
            'tags' => null,
            'status' => 'captured',
            'title' => '整理中…',
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
        ]);
    }

    public function voice(): static
    {
        return $this->state(fn () => [
            'source_type' => 'voice',
            'memory_type' => null,
            'raw_content' => null,
            'summary' => null,
            'structured_data' => null,
            'tags' => null,
            'status' => 'captured',
            'transcription_status' => 'pending',
            'title' => '整理中…',
        ]);
    }
}
