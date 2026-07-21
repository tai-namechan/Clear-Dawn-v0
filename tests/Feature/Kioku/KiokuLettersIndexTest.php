<?php

namespace Tests\Feature\Kioku;

use App\Domain\Kioku\Models\KiokuLetter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class KiokuLettersIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_letters_index_shows_owner_live_history_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $mine = KiokuLetter::factory()->daily('2026-07-15', 2)->create([
            'user_id' => $user->id,
            'status' => KiokuLetter::STATUS_PUBLISHED,
        ]);
        KiokuLetter::factory()->daily('2026-07-14', 0)->empty()->create([
            'user_id' => $user->id,
        ]);
        KiokuLetter::factory()->daily('2026-07-15', 1)->create([
            'user_id' => $other->id,
            'status' => KiokuLetter::STATUS_PUBLISHED,
        ]);

        $this->actingAs($user)
            ->get(route('kioku.letters.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Kioku/Letters')
                ->has('letters', 2)
                ->where('letters.0.id', $mine->id)
                ->where('testLetters', [])
            );
    }

    public function test_home_does_not_include_test_letters_while_letters_index_does(): void
    {
        $user = User::factory()->create();
        $live = KiokuLetter::factory()->daily('2026-07-16', 1)->create([
            'user_id' => $user->id,
        ]);
        $test = KiokuLetter::factory()->testMode()->create([
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('kioku.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('letters', 1)
                ->where('letters.0.id', $live->id)
                ->missing('testLetters')
            );

        $this->actingAs($user)
            ->get(route('kioku.letters.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('letters', 1)
                ->where('letters.0.id', $live->id)
                ->has('testLetters', 1)
                ->where('testLetters.0.id', $test->id)
            );
    }
}
