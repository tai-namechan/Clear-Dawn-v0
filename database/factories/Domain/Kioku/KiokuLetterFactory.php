<?php

namespace Database\Factories\Domain\Kioku;

use App\Domain\Kioku\KiokuLetterCadence;
use App\Domain\Kioku\KiokuLetterMode;
use App\Domain\Kioku\Models\KiokuLetter;
use App\Models\User;
use Carbon\CarbonImmutable;
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
        $weekStart = now()->startOfWeek()->toDateString();

        return [
            'user_id' => User::factory(),
            'week_start' => $weekStart,
            'mode' => KiokuLetterMode::Live->value,
            'cadence' => KiokuLetterCadence::Weekly->value,
            'delivery_date' => $weekStart,
            'dedupe_key' => 'weekly:'.$weekStart,
            'pilot_day' => null,
            'status' => KiokuLetter::STATUS_PUBLISHED,
            'character_variant' => 'shiori',
            'intro' => '今週の記憶を眺めると、記録の習慣が続いています。',
            'context' => null,
            'candidate_count' => 5,
            'item_count' => 0,
            'prompt_key' => 'kioku.concierge.letter.v1',
            'model' => 'test-strong-model',
            'generation_meta' => null,
            'retry_count' => 0,
            'generated_at' => now(),
            'published_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (KiokuLetter $letter): void {
            $attrs = $letter->getAttributes();
            $mode = $attrs['mode'] ?? KiokuLetterMode::Live->value;
            $cadence = $attrs['cadence'] ?? KiokuLetterCadence::Weekly->value;

            if ($mode === KiokuLetterMode::Test->value) {
                if (empty($attrs['dedupe_key']) || ! str_starts_with((string) $attrs['dedupe_key'], 'test:')) {
                    $letter->setAttribute('dedupe_key', 'test:'.(string) str()->ulid());
                }
                if (empty($attrs['delivery_date'])) {
                    $letter->setAttribute(
                        'delivery_date',
                        $attrs['week_start'] ?? now()->toDateString(),
                    );
                }

                return;
            }

            if ($cadence === KiokuLetterCadence::Daily->value) {
                // Prefer explicit delivery_date for daily letters.
                $day = CarbonImmutable::parse((string) ($attrs['delivery_date'] ?? $attrs['week_start'] ?? now()))->toDateString();
                $letter->setAttribute('delivery_date', $day);
                $letter->setAttribute('week_start', CarbonImmutable::parse($day)->startOfWeek()->toDateString());
                $letter->setAttribute('dedupe_key', 'daily:'.$day);

                return;
            }

            // Prefer explicit week_start for weekly letters so test overrides stick.
            $week = CarbonImmutable::parse((string) ($attrs['week_start'] ?? $attrs['delivery_date'] ?? now()))
                ->startOfWeek()
                ->toDateString();
            $letter->setAttribute('week_start', $week);
            $letter->setAttribute('delivery_date', $week);
            $letter->setAttribute('dedupe_key', 'weekly:'.$week);
        });
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

    public function daily(string $deliveryDate, ?int $pilotDay = null): static
    {
        return $this->state(fn () => [
            'cadence' => KiokuLetterCadence::Daily->value,
            'delivery_date' => $deliveryDate,
            'pilot_day' => $pilotDay,
        ]);
    }

    public function testMode(): static
    {
        return $this->state(fn () => [
            'mode' => KiokuLetterMode::Test->value,
            'test_expires_at' => now()->addDays(7),
        ]);
    }
}
