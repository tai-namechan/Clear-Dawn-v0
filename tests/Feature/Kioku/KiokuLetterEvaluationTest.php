<?php

namespace Tests\Feature\Kioku;

use App\Domain\Kioku\Models\KiokuLetter;
use App\Domain\Kioku\Models\KiokuLetterItem;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Services\KiokuLetterCandidateService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KiokuLetterEvaluationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{user: User, letter: KiokuLetter, items: list<KiokuLetterItem>}
     */
    private function publishedLetter(int $itemCount = 2, string $character = 'shiori'): array
    {
        $user = User::factory()->create();
        $letter = KiokuLetter::factory()->create([
            'user_id' => $user->id,
            'character_variant' => $character,
            'item_count' => $itemCount,
        ]);

        $items = [];
        foreach (range(1, $itemCount) as $position) {
            $memory = Memory::factory()->create([
                'user_id' => $user->id,
                'captured_at' => now()->subDays(30),
            ]);
            $items[] = KiokuLetterItem::factory()->create([
                'letter_id' => $letter->id,
                'memory_id' => $memory->id,
                'position' => $position,
            ]);
        }

        return ['user' => $user, 'letter' => $letter, 'items' => $items];
    }

    public function test_owner_sees_letter_and_strangers_get_404(): void
    {
        ['user' => $user, 'letter' => $letter] = $this->publishedLetter();

        $this->actingAs($user)
            ->get(route('kioku.letters.show', $letter))
            ->assertOk();

        $this->actingAs(User::factory()->create())
            ->get(route('kioku.letters.show', $letter))
            ->assertNotFound();
    }

    public function test_first_open_marks_references_exactly_once(): void
    {
        ['user' => $user, 'letter' => $letter, 'items' => $items] = $this->publishedLetter();
        $memory = $items[0]->memory()->withoutUserScope()->sole();
        $this->assertSame(0, $memory->referenced_count);
        $this->assertNull($memory->last_referenced_at);

        $this->actingAs($user)
            ->post(route('kioku.letters.open', $letter))
            ->assertRedirect(route('kioku.letters.show', $letter));

        $letter->refresh();
        $this->assertSame(KiokuLetter::STATUS_OPENED, $letter->status);
        $this->assertNotNull($letter->opened_at);

        $memory->refresh();
        $this->assertSame(1, $memory->referenced_count);
        $this->assertNotNull($memory->last_referenced_at);

        // Reload / duplicate open never double-counts.
        $this->actingAs($user)->post(route('kioku.letters.open', $letter));
        $this->assertSame(1, $memory->fresh()->referenced_count);
    }

    public function test_open_on_empty_letter_records_time_without_references(): void
    {
        $user = User::factory()->create();
        $letter = KiokuLetter::factory()->empty()->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('kioku.letters.open', $letter));

        $letter->refresh();
        $this->assertSame(KiokuLetter::STATUS_EMPTY, $letter->status);
        $this->assertNotNull($letter->opened_at);
    }

    public function test_other_users_cannot_open_or_judge(): void
    {
        ['letter' => $letter, 'items' => $items] = $this->publishedLetter();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->post(route('kioku.letters.open', $letter))
            ->assertNotFound();

        $this->actingAs($stranger)
            ->put(route('kioku.letters.items.verdict', [$letter, $items[0]]), [
                'verdict' => 'hit',
            ])
            ->assertNotFound();
    }

    public function test_item_of_another_letter_is_404(): void
    {
        ['user' => $user, 'letter' => $letter] = $this->publishedLetter();
        ['items' => $otherItems] = $this->publishedLetter();

        $this->actingAs($user)
            ->put(route('kioku.letters.items.verdict', [$letter, $otherItems[0]]), [
                'verdict' => 'hit',
            ])
            ->assertNotFound();
    }

    public function test_verdicts_are_limited_to_the_four_known_values(): void
    {
        ['user' => $user, 'letter' => $letter, 'items' => $items] = $this->publishedLetter();

        foreach (KiokuLetterItem::VERDICTS as $verdict) {
            $this->actingAs($user)
                ->put(route('kioku.letters.items.verdict', [$letter, $items[0]]), [
                    'verdict' => $verdict,
                ])
                ->assertRedirect(route('kioku.letters.show', $letter));

            $this->assertSame($verdict, $items[0]->fresh()->verdict);
        }

        $this->actingAs($user)
            ->from(route('kioku.letters.show', $letter))
            ->put(route('kioku.letters.items.verdict', [$letter, $items[0]]), [
                'verdict' => 'amazing',
            ])
            ->assertSessionHasErrors('verdict');
    }

    public function test_first_verdict_moves_letter_to_evaluating(): void
    {
        ['user' => $user, 'letter' => $letter, 'items' => $items] = $this->publishedLetter();

        $this->actingAs($user)->post(route('kioku.letters.open', $letter));
        $this->actingAs($user)->put(
            route('kioku.letters.items.verdict', [$letter, $items[0]]),
            ['verdict' => 'hit', 'note' => '完全に忘れていた'],
        );

        $letter->refresh();
        $this->assertSame(KiokuLetter::STATUS_EVALUATING, $letter->status);
        $this->assertSame('完全に忘れていた', $items[0]->fresh()->verdict_note);
        $this->assertNotNull($items[0]->fresh()->verdict_at);
    }

    public function test_sensitive_leak_halts_the_letter(): void
    {
        ['user' => $user, 'letter' => $letter, 'items' => $items] = $this->publishedLetter();

        $this->actingAs($user)->put(
            route('kioku.letters.items.verdict', [$letter, $items[0]]),
            ['verdict' => KiokuLetterItem::VERDICT_SENSITIVE_LEAK],
        );

        $this->assertSame(KiokuLetter::STATUS_HALTED, $letter->fresh()->status);
    }

    public function test_complete_creates_exactly_one_evaluation_memory(): void
    {
        ['user' => $user, 'letter' => $letter, 'items' => $items] = $this->publishedLetter();
        KiokuLetter::query()->withoutUserScope()->whereKey($letter->id)->update([
            'published_at' => now()->subHours(3),
            'intro' => '入力を守る段階から価値を返す段階へ。',
        ]);

        $this->actingAs($user)->post(route('kioku.letters.open', $letter));
        $this->actingAs($user)->put(
            route('kioku.letters.items.verdict', [$letter, $items[0]]),
            ['verdict' => 'hit'],
        );
        $this->actingAs($user)->put(
            route('kioku.letters.items.verdict', [$letter, $items[1]]),
            ['verdict' => 'soft_hit'],
        );

        $this->actingAs($user)
            ->post(route('kioku.letters.complete', $letter))
            ->assertRedirect(route('kioku.letters.show', $letter));

        $letter->refresh();
        $this->assertSame(KiokuLetter::STATUS_EVALUATED, $letter->status);
        $this->assertNotNull($letter->completed_at);
        $this->assertSame('shiori', $letter->character_variant);

        $evaluation = Memory::query()
            ->withoutUserScope()
            ->where('source_type', 'kioku_letter')
            ->sole();
        $this->assertSame($evaluation->id, $letter->evaluation_memory_id);
        $this->assertSame('ready', $evaluation->status);
        $this->assertFalse($evaluation->sensitive);
        $this->assertStringContainsString('コンシェルジュ手紙 第1週（', $evaluation->title);
        $this->assertStringContainsString($items[0]->headline, (string) $evaluation->raw_content);
        $this->assertContains('コンシェルジュ実験', $evaluation->tags ?? []);

        $data = $evaluation->structured_data;
        $this->assertSame('kioku_concierge_v1', $data['experiment']);
        $this->assertSame($letter->week_start->toDateString(), $data['week_start']);
        $this->assertSame('shiori', $data['character_variant']);
        $this->assertTrue($data['opened_within_24h']);
        // JSON round-trip may decode whole-number rates as int.
        $this->assertEquals(0.5, $data['hit_rate']);
        $this->assertEquals(1.0, $data['useful_rate']);
        $this->assertCount(2, $data['items']);

        // A second complete never creates a second memory.
        $this->actingAs($user)->post(route('kioku.letters.complete', $letter));
        $this->assertSame(
            1,
            Memory::query()->withoutUserScope()->where('source_type', 'kioku_letter')->count(),
        );
    }

    public function test_complete_requires_every_item_to_be_judged(): void
    {
        ['user' => $user, 'letter' => $letter, 'items' => $items] = $this->publishedLetter();

        $this->actingAs($user)->put(
            route('kioku.letters.items.verdict', [$letter, $items[0]]),
            ['verdict' => 'hit'],
        );

        $this->actingAs($user)->post(route('kioku.letters.complete', $letter));

        $this->assertNull($letter->fresh()->completed_at);
        $this->assertSame(
            0,
            Memory::query()->withoutUserScope()->where('source_type', 'kioku_letter')->count(),
        );
    }

    public function test_verdicts_are_frozen_after_completion(): void
    {
        ['user' => $user, 'letter' => $letter, 'items' => $items] = $this->publishedLetter(1);

        $this->actingAs($user)->put(
            route('kioku.letters.items.verdict', [$letter, $items[0]]),
            ['verdict' => 'hit'],
        );
        $this->actingAs($user)->post(route('kioku.letters.complete', $letter));

        $this->actingAs($user)->put(
            route('kioku.letters.items.verdict', [$letter, $items[0]]),
            ['verdict' => 'miss'],
        );

        $this->assertSame('hit', $items[0]->fresh()->verdict);
    }

    public function test_halted_letter_keeps_halted_status_after_completion(): void
    {
        ['user' => $user, 'letter' => $letter, 'items' => $items] = $this->publishedLetter(1);

        $this->actingAs($user)->put(
            route('kioku.letters.items.verdict', [$letter, $items[0]]),
            ['verdict' => KiokuLetterItem::VERDICT_SENSITIVE_LEAK],
        );
        $this->actingAs($user)->post(route('kioku.letters.complete', $letter));

        $letter->refresh();
        $this->assertSame(KiokuLetter::STATUS_HALTED, $letter->status);
        $this->assertNotNull($letter->completed_at);
        $this->assertNotNull($letter->evaluation_memory_id);
    }

    public function test_evaluation_memory_never_becomes_a_candidate(): void
    {
        ['user' => $user, 'letter' => $letter, 'items' => $items] = $this->publishedLetter(1);

        $this->actingAs($user)->put(
            route('kioku.letters.items.verdict', [$letter, $items[0]]),
            ['verdict' => 'hit'],
        );
        $this->actingAs($user)->post(route('kioku.letters.complete', $letter));

        // Even far outside the cooldown window it stays excluded by source.
        Memory::query()
            ->withoutUserScope()
            ->where('source_type', 'kioku_letter')
            ->update(['captured_at' => now()->subDays(60)]);

        $candidates = app(KiokuLetterCandidateService::class)->candidatesFor((int) $user->id);

        $this->assertFalse(
            $candidates->contains(fn (Memory $memory) => $memory->source_type === 'kioku_letter'),
        );
    }

    public function test_home_shows_recent_letters_summary(): void
    {
        ['user' => $user, 'letter' => $letter, 'items' => $items] = $this->publishedLetter();
        KiokuLetter::factory()->create([
            'user_id' => $user->id,
            'week_start' => now()->subWeeks(1)->startOfWeek()->toDateString(),
            'status' => KiokuLetter::STATUS_FAILED,
        ]);

        $this->actingAs($user)->put(
            route('kioku.letters.items.verdict', [$letter, $items[0]]),
            ['verdict' => 'hit'],
        );

        $this->actingAs($user)
            ->get(route('kioku.home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Kioku/Index')
                ->has('letters', 1)
                ->where('letters.0.id', $letter->id)
                ->where('letters.0.judged_count', 1)
                ->where('letters.0.hit_count', 1)
                ->where('letters.0.item_count', 2)
                ->where('letters.0.character_variant', 'shiori'),
            );
    }
}
