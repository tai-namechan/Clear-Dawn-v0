<?php

namespace Tests\Feature\Kioku;

use App\Domain\Kioku\Jobs\EnrichMemoryJob;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Models\MemoryAsset;
use App\Domain\Kioku\Models\MemoryLink;
use App\Domain\Kioku\Services\KiokuTagNormalizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MemoryTagsUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_tags_through_normalizer(): void
    {
        Bus::fake([EnrichMemoryJob::class]);
        Http::fake();

        $user = User::factory()->create();
        $memory = Memory::factory()->create([
            'user_id' => $user->id,
            'raw_content' => '原文は不変',
            'transcript_text' => '文字起こしも不変',
            'summary' => '要約は触らない',
            'tags' => ['古い'],
            'status' => 'ready',
        ]);

        $this->actingAs($user)
            ->put(route('kioku.memories.tags.update', $memory), [
                'tags' => ['#ヨガ', '  仕事  ', 'ヨガ'],
            ])
            ->assertRedirect(route('kioku.memories.show', $memory));

        $memory->refresh();
        $this->assertSame(['ヨガ', '仕事'], $memory->tags);
        $this->assertSame('原文は不変', $memory->raw_content);
        $this->assertSame('文字起こしも不変', $memory->transcript_text);
        $this->assertSame('要約は触らない', $memory->summary);

        Bus::assertNotDispatched(EnrichMemoryJob::class);
        Http::assertNothingSent();
    }

    public function test_empty_array_clears_all_tags(): void
    {
        $user = User::factory()->create();
        $memory = Memory::factory()->create([
            'user_id' => $user->id,
            'tags' => ['残さない'],
            'status' => 'ready',
        ]);

        $this->actingAs($user)
            ->put(route('kioku.memories.tags.update', $memory), [
                'tags' => [],
            ])
            ->assertRedirect(route('kioku.memories.show', $memory));

        $this->assertNull($memory->fresh()->tags);
    }

    public function test_other_user_gets_not_found(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $memory = Memory::factory()->create([
            'user_id' => $owner->id,
            'tags' => ['秘密'],
            'status' => 'ready',
        ]);

        $this->actingAs($other)
            ->put(route('kioku.memories.tags.update', $memory), [
                'tags' => ['書き換え'],
            ])
            ->assertNotFound();

        $this->assertSame(['秘密'], $memory->fresh()->tags);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $memory = Memory::factory()->create([
            'tags' => ['秘密'],
            'status' => 'ready',
        ]);

        $this->put(route('kioku.memories.tags.update', $memory), [
            'tags' => ['ゲスト'],
        ])->assertRedirect(route('login'));
    }

    public function test_tags_update_route_is_behind_auth_and_verified_middleware(): void
    {
        $middleware = app('router')
            ->getRoutes()
            ->getByName('kioku.memories.tags.update')
            ?->gatherMiddleware() ?? [];

        $this->assertContains('auth', $middleware);
        $this->assertContains('verified', $middleware);

        // User does not implement MustVerifyEmail, so EnsureEmailIsVerified is
        // currently a no-op (same as other Kioku routes). Keep the middleware
        // registered so enabling MustVerifyEmail later covers this endpoint.
        $user = User::factory()->unverified()->create();
        $memory = Memory::factory()->create([
            'user_id' => $user->id,
            'tags' => ['検証前'],
            'status' => 'ready',
        ]);

        $this->actingAs($user)
            ->put(route('kioku.memories.tags.update', $memory), [
                'tags' => ['検証後'],
            ])
            ->assertRedirect(route('kioku.memories.show', $memory));

        $this->assertSame(['検証後'], $memory->fresh()->tags);
    }

    public function test_validation_rejects_over_max_count_and_length(): void
    {
        $user = User::factory()->create();
        $memory = Memory::factory()->create([
            'user_id' => $user->id,
            'tags' => ['既存'],
            'status' => 'ready',
        ]);

        $tooMany = [];
        for ($i = 1; $i <= KiokuTagNormalizer::MAX_TAGS + 1; $i++) {
            $tooMany[] = "tag{$i}";
        }

        $this->actingAs($user)
            ->from(route('kioku.memories.show', $memory))
            ->put(route('kioku.memories.tags.update', $memory), [
                'tags' => $tooMany,
            ])
            ->assertRedirect(route('kioku.memories.show', $memory))
            ->assertSessionHasErrors('tags');

        $this->actingAs($user)
            ->from(route('kioku.memories.show', $memory))
            ->put(route('kioku.memories.tags.update', $memory), [
                'tags' => [str_repeat('あ', KiokuTagNormalizer::MAX_TAG_CHARS + 1)],
            ])
            ->assertRedirect(route('kioku.memories.show', $memory))
            ->assertSessionHasErrors('tags.0');

        $this->assertSame(['既存'], $memory->fresh()->tags);
    }

    public function test_does_not_mutate_memory_assets_and_refreshes_related_cache(): void
    {
        Bus::fake([EnrichMemoryJob::class]);

        $user = User::factory()->create();
        $memory = Memory::factory()->voice()->create([
            'user_id' => $user->id,
            'status' => 'ready',
            'transcription_status' => 'ready',
            'transcript_text' => '音声の文字起こし',
            'title' => '音声メモ',
            'tags' => ['古い関連'],
            'memory_type' => 'thought',
            'summary' => '要約',
        ]);
        $related = Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '新しい関連先',
            'tags' => ['新しいタグ'],
            'status' => 'ready',
        ]);
        $stale = Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '古い関連先',
            'tags' => ['古い関連'],
            'status' => 'ready',
        ]);

        $asset = MemoryAsset::query()->create([
            'memory_id' => $memory->id,
            'kind' => MemoryAsset::KIND_AUDIO_ORIGINAL,
            'disk' => 'local',
            'path' => 'kioku/audio/test.webm',
            'mime_type' => 'audio/webm',
            'byte_size' => 123,
            'duration_ms' => 1000,
            'checksum' => 'abc',
        ]);

        MemoryLink::query()->create([
            'from_memory_id' => $memory->id,
            'to_memory_id' => $stale->id,
            'kind' => 'related',
            'score' => 9,
            'created_by' => 'system',
        ]);

        $this->actingAs($user)
            ->put(route('kioku.memories.tags.update', $memory), [
                'tags' => ['新しいタグ'],
            ])
            ->assertRedirect(route('kioku.memories.show', $memory));

        $asset->refresh();
        $this->assertSame('kioku/audio/test.webm', $asset->path);
        $this->assertSame(123, $asset->byte_size);
        $this->assertSame('音声の文字起こし', $memory->fresh()->transcript_text);

        $this->assertDatabaseMissing('memory_links', [
            'from_memory_id' => $memory->id,
            'to_memory_id' => $stale->id,
            'created_by' => 'system',
        ]);
        $this->assertDatabaseHas('memory_links', [
            'from_memory_id' => $memory->id,
            'to_memory_id' => $related->id,
            'created_by' => 'system',
        ]);

        Bus::assertNotDispatched(EnrichMemoryJob::class);
    }
}
