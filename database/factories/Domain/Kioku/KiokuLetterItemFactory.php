<?php

namespace Database\Factories\Domain\Kioku;

use App\Domain\Kioku\Models\KiokuLetter;
use App\Domain\Kioku\Models\KiokuLetterItem;
use App\Domain\Kioku\Models\Memory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KiokuLetterItem>
 */
class KiokuLetterItemFactory extends Factory
{
    protected $model = KiokuLetterItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'letter_id' => KiokuLetter::factory(),
            'memory_id' => Memory::factory(),
            'position' => 1,
            'title_snapshot' => fake()->sentence(3),
            'summary_snapshot' => fake()->sentence(),
            'headline' => '30秒保存の次に見るべきもの',
            'why_now' => '実機確認が終わり、記録習慣と自動発火を検証する時期だからです。',
            'related_memory_ids' => null,
            'verdict' => null,
            'verdict_note' => null,
            'verdict_at' => null,
        ];
    }

    public function verdicted(string $verdict = KiokuLetterItem::VERDICT_HIT): static
    {
        return $this->state(fn () => [
            'verdict' => $verdict,
            'verdict_at' => now(),
        ]);
    }
}
