<?php

namespace Tests\Feature\Kioku;

use App\Domain\Kioku\Jobs\EnrichMemoryJob;
use App\Domain\Kioku\Models\Memory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class CaptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_use_capture_endpoint(): void
    {
        $this->postJson(route('kioku.captures.manual'), [
            'client_capture_id' => (string) Str::uuid(),
            'raw_content' => 'ログインしていない',
        ])->assertUnauthorized();
    }

    public function test_manual_capture_creates_memory_and_queues_enrichment(): void
    {
        Bus::fake([EnrichMemoryJob::class]);
        $user = User::factory()->create();
        $captureId = (string) Str::uuid();

        $this->actingAs($user)
            ->postJson(route('kioku.captures.manual'), [
                'client_capture_id' => $captureId,
                'raw_content' => '疲れた23時の一文',
                'captured_at' => '2026-07-12T14:00:00Z',
            ])
            ->assertCreated()
            ->assertJsonPath('created', true)
            ->assertJsonPath('memory.client_capture_id', $captureId)
            ->assertJsonPath('memory.status', 'captured');

        $memory = Memory::query()->withoutUserScope()->where('user_id', $user->id)->sole();
        $this->assertSame('疲れた23時の一文', $memory->raw_content);
        $this->assertSame('manual', $memory->source_type);
        $this->assertSame('整理中…', $memory->title);

        Bus::assertDispatched(EnrichMemoryJob::class);
    }

    public function test_resending_same_client_capture_id_returns_existing_memory(): void
    {
        Bus::fake([EnrichMemoryJob::class]);
        $user = User::factory()->create();
        $captureId = (string) Str::uuid();
        $payload = [
            'client_capture_id' => $captureId,
            'raw_content' => '再送しても1件だけ',
        ];

        $first = $this->actingAs($user)
            ->postJson(route('kioku.captures.manual'), $payload)
            ->assertCreated()
            ->json('memory.id');

        $this->actingAs($user)
            ->postJson(route('kioku.captures.manual'), $payload)
            ->assertOk()
            ->assertJsonPath('created', false)
            ->assertJsonPath('memory.id', $first);

        $this->assertSame(
            1,
            Memory::query()->withoutUserScope()->where('user_id', $user->id)->count(),
        );
        Bus::assertDispatchedTimes(EnrichMemoryJob::class, 1);
    }

    public function test_same_content_with_different_capture_ids_creates_separate_memories(): void
    {
        Bus::fake([EnrichMemoryJob::class]);
        $user = User::factory()->create();

        foreach (range(1, 2) as $ignored) {
            $this->actingAs($user)
                ->postJson(route('kioku.captures.manual'), [
                    'client_capture_id' => (string) Str::uuid(),
                    'raw_content' => '同じ内容',
                ])
                ->assertCreated();
        }

        $this->assertSame(
            2,
            Memory::query()->withoutUserScope()->where('user_id', $user->id)->count(),
        );
    }

    public function test_different_users_can_reuse_the_same_client_capture_id(): void
    {
        Bus::fake([EnrichMemoryJob::class]);
        $captureId = (string) Str::uuid();

        foreach (User::factory()->count(2)->create() as $user) {
            $this->actingAs($user)
                ->postJson(route('kioku.captures.manual'), [
                    'client_capture_id' => $captureId,
                    'raw_content' => 'ユーザーごとに独立',
                ])
                ->assertCreated();
        }

        $this->assertSame(
            2,
            Memory::query()->withoutUserScope()->where('client_capture_id', $captureId)->count(),
        );
    }

    public function test_capture_requires_client_capture_id_and_raw_content(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('kioku.captures.manual'), ['raw_content' => 'IDなし'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['client_capture_id']);

        $this->actingAs($user)
            ->postJson(route('kioku.captures.manual'), [
                'client_capture_id' => (string) Str::uuid(),
                'raw_content' => '   ',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['raw_content']);
    }

    public function test_url_content_is_detected_as_url_source(): void
    {
        Bus::fake([EnrichMemoryJob::class]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('kioku.captures.manual'), [
                'client_capture_id' => (string) Str::uuid(),
                'raw_content' => 'https://laravel.com/docs',
            ])
            ->assertCreated()
            ->assertJsonPath('memory.source_type', 'url');
    }

    public function test_legacy_store_accepts_client_capture_id_and_stays_idempotent(): void
    {
        Bus::fake([EnrichMemoryJob::class]);
        $user = User::factory()->create();
        $captureId = (string) Str::uuid();

        foreach (range(1, 2) as $ignored) {
            $this->actingAs($user)
                ->post(route('kioku.memories.store'), [
                    'client_capture_id' => $captureId,
                    'raw_content' => '旧フォームからの再送',
                ])
                ->assertRedirect(route('kioku.home'));
        }

        $this->assertSame(
            1,
            Memory::query()->withoutUserScope()->where('user_id', $user->id)->count(),
        );
    }

    public function test_legacy_store_without_capture_id_still_works(): void
    {
        Bus::fake([EnrichMemoryJob::class]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('kioku.memories.store'), [
                'raw_content' => 'IDなしの従来保存',
            ])
            ->assertRedirect(route('kioku.home'));

        $memory = Memory::query()->withoutUserScope()->where('user_id', $user->id)->sole();
        $this->assertNull($memory->client_capture_id);
    }

    public function test_raw_content_cannot_be_updated_after_creation(): void
    {
        $user = User::factory()->create();
        $memory = Memory::factory()->create([
            'user_id' => $user->id,
            'raw_content' => '原文',
        ]);

        $this->expectException(LogicException::class);

        $memory->update(['raw_content' => '改ざん']);
    }

    public function test_raw_content_can_be_repaired_with_explicit_flag(): void
    {
        $user = User::factory()->create();
        $memory = Memory::factory()->create([
            'user_id' => $user->id,
            'raw_content' => '壊れた原文',
        ]);

        $memory->permitRawContentRepair();
        $memory->update(['raw_content' => '修復済み原文']);

        $this->assertSame('修復済み原文', $memory->fresh()->raw_content);
    }

    public function test_non_raw_updates_are_still_allowed(): void
    {
        $user = User::factory()->create();
        $memory = Memory::factory()->create([
            'user_id' => $user->id,
            'raw_content' => '原文のまま',
            'status' => 'ready',
        ]);

        $memory->update(['summary' => '新しい要約', 'status' => 'ready']);

        $memory->refresh();
        $this->assertSame('新しい要約', $memory->summary);
        $this->assertSame('原文のまま', $memory->raw_content);
    }

    public function test_capture_event_is_recorded_without_raw_content(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('kioku.captures.events'), [
                'event' => 'local_saved',
                'source_type' => 'manual',
                'duration_ms' => 4200,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('kioku_capture_events', [
            'user_id' => $user->id,
            'event' => 'local_saved',
            'source_type' => 'manual',
            'duration_ms' => 4200,
        ]);
    }

    public function test_capture_event_rejects_unknown_event_names(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('kioku.captures.events'), [
                'event' => 'raw_content_dump',
                'source_type' => 'manual',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['event']);
    }
}
