<?php

namespace Database\Factories\Domain\Kioku;

use App\Domain\Kioku\Models\KiokuLetter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KiokuLetter>
 */
class KiokuLetterFactory extends Factory
{
    protected $model = KiokuLetter::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'week_start' => now()->startOfWeek()->toDateString(),
            'status' => KiokuLetter::STATUS_PUBLISHED,
            'character_variant' => 'shiori',
            'intro' => '今週の記憶を眺めると、記録の習慣が続いています。',
            'context' => null,
            'candidate_count' => 5,
            'item_count' => 0,
            'prompt_key' => 'kioku.concierge.letter.v1',
            'model' => 'test-strong-model',
            'generation_meta' => null,
            'generated_at' => now(),
            'published_at' => now(),
        ];
    }

    public function nagi(): static
    {
        return $this->state(fn () => ['character_variant' => 'nagi']);
    }

    public function empty(): static
    {
        return $this->state(fn () => [
            'status' => KiokuLetter::STATUS_EMPTY,
            'intro' => null,
            'item_count' => 0,
        ]);
    }

    public function opened(): static
    {
        return $this->state(fn () => [
            'status' => KiokuLetter::STATUS_OPENED,
            'opened_at' => now(),
        ]);
    }
}
