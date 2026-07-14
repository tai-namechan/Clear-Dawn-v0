<?php

namespace Tests\Feature\Settings;

use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Models\MemoryAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_account_deletion_removes_kioku_audio_files_for_that_user_only(): void
    {
        config(['kioku.audio.disk' => 'local']);
        Storage::fake('local');

        $owner = User::factory()->create();
        $other = User::factory()->create();

        $ownerMemory = Memory::factory()->voice()->create(['user_id' => $owner->id]);
        $ownerPath = 'kioku-audio/'.$owner->id.'/voice.wav';
        Storage::disk('local')->put($ownerPath, 'owner-audio');
        MemoryAsset::query()->create([
            'memory_id' => $ownerMemory->id,
            'kind' => MemoryAsset::KIND_AUDIO_ORIGINAL,
            'disk' => 'local',
            'path' => $ownerPath,
            'mime_type' => 'audio/wav',
            'byte_size' => 11,
            'duration_ms' => 5000,
        ]);
        // Orphan file under the same user prefix (DB row already gone / never linked).
        $orphanPath = 'kioku-audio/'.$owner->id.'/orphan.wav';
        Storage::disk('local')->put($orphanPath, 'orphan-audio');

        $otherMemory = Memory::factory()->voice()->create(['user_id' => $other->id]);
        $otherPath = 'kioku-audio/'.$other->id.'/voice.wav';
        Storage::disk('local')->put($otherPath, 'other-audio');
        MemoryAsset::query()->create([
            'memory_id' => $otherMemory->id,
            'kind' => MemoryAsset::KIND_AUDIO_ORIGINAL,
            'disk' => 'local',
            'path' => $otherPath,
            'mime_type' => 'audio/wav',
            'byte_size' => 11,
            'duration_ms' => 5000,
        ]);

        $this->actingAs($owner)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ])
            ->assertRedirect(route('home'));

        $this->assertNull($owner->fresh());
        $this->assertSame(0, Memory::query()->withoutUserScope()->where('user_id', $owner->id)->count());
        $this->assertSame(0, MemoryAsset::query()->where('memory_id', $ownerMemory->id)->count());
        Storage::disk('local')->assertMissing($ownerPath);
        Storage::disk('local')->assertMissing($orphanPath);

        $this->assertNotNull($other->fresh());
        $this->assertSame(1, Memory::query()->withoutUserScope()->where('user_id', $other->id)->count());
        Storage::disk('local')->assertExists($otherPath);
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}
