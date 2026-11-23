<?php

namespace Tests\Feature\Kioku;

use App\Domain\Kioku\Models\KiokuLetter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneTestKiokuLettersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_expired_only_prune_removes_expired_test_letters_for_all_users(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $firstExpired = KiokuLetter::factory()->testMode()->create([
            'user_id' => $firstUser->id,
            'test_expires_at' => now()->subMinute(),
        ]);
        $secondExpired = KiokuLetter::factory()->testMode()->create([
            'user_id' => $secondUser->id,
            'test_expires_at' => now()->subMinute(),
        ]);
        $unexpired = KiokuLetter::factory()->testMode()->create([
            'user_id' => $firstUser->id,
            'test_expires_at' => now()->addMinute(),
        ]);
        $live = KiokuLetter::factory()->create(['user_id' => $firstUser->id]);

        $this->artisan('kioku:letters:test:prune', ['--expired-only' => true])
            ->assertSuccessful();

        $this->assertModelMissing($firstExpired);
        $this->assertModelMissing($secondExpired);
        $this->assertModelExists($unexpired);
        $this->assertModelExists($live);
    }

    public function test_global_prune_requires_expired_only_guard(): void
    {
        $letter = KiokuLetter::factory()->testMode()->create();

        $this->artisan('kioku:letters:test:prune')->assertFailed();

        $this->assertModelExists($letter);
    }
}
