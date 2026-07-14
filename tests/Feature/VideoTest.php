<?php

namespace Tests\Feature;

use App\Enums\VideoStatus;
use App\Models\User;
use App\Models\Video;
use App\Queries\GetVideosQuery;
use App\Services\VideoStorageClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class VideoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function uploadUrlPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'テスト動画',
            'mime_type' => 'video/mp4',
            'size_bytes' => 1_000_000,
            'duration_seconds' => 30,
        ], $overrides);
    }

    private function mockStorageClient(): MockInterface&VideoStorageClient
    {
        /** @var MockInterface&VideoStorageClient $mock */
        $mock = Mockery::mock(VideoStorageClient::class);
        $this->app->instance(VideoStorageClient::class, $mock);

        return $mock;
    }

    public function test_upload_url_creates_pending_video_with_server_generated_storage_key(): void
    {
        $user = User::factory()->create();
        $storage = $this->mockStorageClient();

        $storage->shouldReceive('temporaryUploadUrl')
            ->once()
            ->with(
                Mockery::on(fn (string $key) => (bool) preg_match('/^videos\/'.$user->id.'\/[0-9A-Z]{26}\.mp4$/', $key)),
                15,
                'video/mp4',
            )
            ->andReturn([
                'url' => 'https://example.test/upload',
                'headers' => ['Content-Type' => 'video/mp4'],
                'expires_at' => now()->addMinutes(15)->toIso8601String(),
            ]);

        $response = $this->actingAs($user)->postJson(route('videos.upload-url'), $this->uploadUrlPayload());

        $response->assertOk()
            ->assertJsonPath('mode', 'single')
            ->assertJsonStructure(['video_id', 'uploads' => [['url', 'headers', 'expires_at']]]);

        $video = Video::query()->firstOrFail();

        $this->assertSame(VideoStatus::Pending, $video->status);
        $this->assertSame($user->id, $video->user_id);
        $this->assertMatchesRegularExpression('/^videos\/'.$user->id.'\/[0-9A-Z]{26}\.mp4$/', $video->storage_key);
    }

    public function test_upload_url_does_not_accept_client_supplied_storage_key(): void
    {
        $user = User::factory()->create();
        $storage = $this->mockStorageClient();

        $storage->shouldReceive('temporaryUploadUrl')
            ->once()
            ->with(
                Mockery::on(fn (string $key) => ! str_contains($key, 'evil-key')),
                Mockery::any(),
                Mockery::any(),
            )
            ->andReturn([
                'url' => 'https://example.test/upload',
                'headers' => ['Content-Type' => 'video/mp4'],
                'expires_at' => now()->addMinutes(15)->toIso8601String(),
            ]);

        $this->actingAs($user)->postJson(route('videos.upload-url'), [
            ...$this->uploadUrlPayload(),
            'storage_key' => 'videos/999/evil-key.mp4',
        ])->assertOk();

        $this->assertDatabaseMissing('videos', [
            'storage_key' => 'videos/999/evil-key.mp4',
        ]);
    }

    public function test_upload_url_is_throttled_to_ten_requests_per_minute(): void
    {
        $user = User::factory()->create();
        $storage = $this->mockStorageClient();

        $storage->shouldReceive('temporaryUploadUrl')
            ->times(10)
            ->andReturn([
                'url' => 'https://example.test/upload',
                'headers' => ['Content-Type' => 'video/mp4'],
                'expires_at' => now()->addMinutes(15)->toIso8601String(),
            ]);

        for ($i = 0; $i < 10; $i++) {
            $response = $this->actingAs($user)->postJson(route('videos.upload-url'), $this->uploadUrlPayload([
                'title' => "動画 {$i}",
            ]));

            $response->assertOk();

            Video::query()->whereKey($response->json('video_id'))->forceDelete();
        }

        $this->actingAs($user)->postJson(route('videos.upload-url'), $this->uploadUrlPayload([
            'title' => '11本目',
        ]))->assertStatus(429);
    }

    public function test_upload_url_returns_422_when_pending_limit_is_reached(): void
    {
        $user = User::factory()->create();
        Video::factory()->count(5)->pending()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->postJson(route('videos.upload-url'), $this->uploadUrlPayload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('upload');
    }

    public function test_user_can_refresh_upload_url_for_own_pending_video(): void
    {
        $user = User::factory()->create();
        $video = Video::factory()->pending()->create([
            'user_id' => $user->id,
            'storage_key' => "videos/{$user->id}/01JTEST0000000000000000000.mp4",
            'mime_type' => 'video/mp4',
        ]);

        $storage = $this->mockStorageClient();
        $storage->shouldReceive('temporaryUploadUrl')
            ->once()
            ->with($video->storage_key, 15, 'video/mp4')
            ->andReturn([
                'url' => 'https://example.test/upload-refreshed',
                'headers' => ['Content-Type' => 'video/mp4'],
                'expires_at' => now()->addMinutes(15)->toIso8601String(),
            ]);

        $this->actingAs($user)
            ->postJson(route('videos.refresh-upload-url', $video))
            ->assertOk()
            ->assertJsonPath('video_id', $video->id)
            ->assertJsonPath('uploads.0.url', 'https://example.test/upload-refreshed');
    }

    public function test_user_cannot_access_another_users_videos(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $pending = Video::factory()->pending()->create(['user_id' => $otherUser->id]);
        $ready = Video::factory()->ready()->create(['user_id' => $otherUser->id]);

        $storage = $this->mockStorageClient();
        $storage->shouldReceive('temporaryUrl')->never();
        $storage->shouldReceive('delete')->never();

        $this->actingAs($user)->postJson(route('videos.refresh-upload-url', $pending))->assertForbidden();
        $this->actingAs($user)->postJson(route('videos.finalize', $pending))->assertForbidden();
        $this->actingAs($user)->postJson(route('videos.finalize', $ready))->assertForbidden();
        $this->actingAs($user)->getJson(route('videos.stream-url', $ready))->assertForbidden();
        $this->actingAs($user)->patchJson(route('videos.update', $ready), ['title' => '改ざん'])->assertForbidden();
        $this->actingAs($user)->deleteJson(route('videos.destroy', $ready))->assertForbidden();
    }

    public function test_finalize_promotes_pending_video_to_ready_and_overwrites_size_bytes(): void
    {
        $user = User::factory()->create();
        $video = Video::factory()->pending()->create([
            'user_id' => $user->id,
            'size_bytes' => 999,
            'storage_key' => "videos/{$user->id}/01JTEST0000000000000000001.mp4",
        ]);

        $storage = $this->mockStorageClient();
        $storage->shouldReceive('exists')->once()->with($video->storage_key)->andReturnTrue();
        $storage->shouldReceive('size')->once()->with($video->storage_key)->andReturn(5_000_000);
        $storage->shouldReceive('mimeType')->once()->with($video->storage_key)->andReturn('video/mp4');
        $storage->shouldReceive('delete')->never();

        $this->actingAs($user)
            ->postJson(route('videos.finalize', $video))
            ->assertOk()
            ->assertJsonPath('status', VideoStatus::Ready->value);

        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
            'status' => VideoStatus::Ready->value,
            'size_bytes' => 5_000_000,
        ]);
    }

    public function test_finalize_deletes_pending_video_when_storage_object_is_missing(): void
    {
        $user = User::factory()->create();
        $video = Video::factory()->pending()->create(['user_id' => $user->id]);

        $storage = $this->mockStorageClient();
        $storage->shouldReceive('exists')->once()->with($video->storage_key)->andReturnFalse();
        $storage->shouldReceive('delete')->once()->with($video->storage_key);
        $storage->shouldReceive('size')->never();
        $storage->shouldReceive('mimeType')->never();

        $this->actingAs($user)
            ->postJson(route('videos.finalize', $video))
            ->assertStatus(422)
            ->assertJsonValidationErrors('finalize');

        $this->assertDatabaseMissing('videos', ['id' => $video->id]);
    }

    public function test_finalize_deletes_pending_video_when_size_exceeds_limit(): void
    {
        $user = User::factory()->create();
        $video = Video::factory()->pending()->create(['user_id' => $user->id]);

        $storage = $this->mockStorageClient();
        $storage->shouldReceive('exists')->once()->with($video->storage_key)->andReturnTrue();
        $storage->shouldReceive('size')->once()->with($video->storage_key)->andReturn(VideoStorageClient::MaxSizeBytes + 1);
        $storage->shouldReceive('delete')->once()->with($video->storage_key);
        $storage->shouldReceive('mimeType')->never();

        $this->actingAs($user)
            ->postJson(route('videos.finalize', $video))
            ->assertStatus(422);

        $this->assertDatabaseMissing('videos', ['id' => $video->id]);
    }

    public function test_finalize_deletes_pending_video_when_mime_type_is_invalid(): void
    {
        $user = User::factory()->create();
        $video = Video::factory()->pending()->create(['user_id' => $user->id]);

        $storage = $this->mockStorageClient();
        $storage->shouldReceive('exists')->once()->with($video->storage_key)->andReturnTrue();
        $storage->shouldReceive('size')->once()->with($video->storage_key)->andReturn(1_000_000);
        $storage->shouldReceive('mimeType')->once()->with($video->storage_key)->andReturn('video/x-msvideo');
        $storage->shouldReceive('delete')->once()->with($video->storage_key);

        $this->actingAs($user)
            ->postJson(route('videos.finalize', $video))
            ->assertStatus(422);

        $this->assertDatabaseMissing('videos', ['id' => $video->id]);
    }

    public function test_finalize_is_idempotent_for_ready_videos(): void
    {
        $user = User::factory()->create();
        $video = Video::factory()->ready()->create([
            'user_id' => $user->id,
            'size_bytes' => 2_000_000,
        ]);

        $storage = $this->mockStorageClient();
        $storage->shouldReceive('exists')->never();
        $storage->shouldReceive('size')->never();
        $storage->shouldReceive('mimeType')->never();

        $this->actingAs($user)->postJson(route('videos.finalize', $video))->assertOk();
        $this->actingAs($user)->postJson(route('videos.finalize', $video))->assertOk();

        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
            'status' => VideoStatus::Ready->value,
            'size_bytes' => 2_000_000,
        ]);
    }

    public function test_get_videos_query_returns_only_ready_videos_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $ready = Video::factory()->ready()->create(['user_id' => $user->id, 'title' => '公開済み']);
        Video::factory()->pending()->create(['user_id' => $user->id, 'title' => 'アップロード中']);
        Video::factory()->ready()->create(['title' => '他人の動画']);
        Video::factory()->ready()->create(['user_id' => $user->id, 'title' => '削除済み'])->delete();

        $paginator = app(GetVideosQuery::class)->handle($user);

        $this->assertCount(1, $paginator);
        $this->assertSame($ready->id, $paginator->first()->id);
    }

    public function test_stream_url_is_available_for_ready_videos_and_includes_expires_at(): void
    {
        $user = User::factory()->create();
        $video = Video::factory()->ready()->create(['user_id' => $user->id]);
        $expiresAt = now()->addMinutes(10)->toIso8601String();

        $storage = $this->mockStorageClient();
        $storage->shouldReceive('temporaryUrl')
            ->once()
            ->with($video->storage_key, 10)
            ->andReturn([
                'url' => 'https://example.test/stream',
                'expires_at' => $expiresAt,
            ]);

        $this->actingAs($user)
            ->getJson(route('videos.stream-url', $video))
            ->assertOk()
            ->assertJson([
                'url' => 'https://example.test/stream',
                'expires_at' => $expiresAt,
            ]);
    }

    public function test_prune_command_deletes_only_stale_pending_videos_in_storage_first_order(): void
    {
        Carbon::setTestNow('2026-07-07 12:00:00');

        $user = User::factory()->create();
        $stalePending = Video::factory()->pending()->create([
            'user_id' => $user->id,
            'created_at' => now()->subHours(25),
            'updated_at' => now()->subHours(25),
        ]);
        $recentPending = Video::factory()->pending()->create([
            'user_id' => $user->id,
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);
        $ready = Video::factory()->ready()->create([
            'user_id' => $user->id,
            'created_at' => now()->subHours(30),
            'updated_at' => now()->subHours(30),
        ]);

        $storage = $this->mockStorageClient();
        $storage->shouldReceive('delete')
            ->once()
            ->with($stalePending->storage_key);

        Artisan::call('videos:prune-pending');

        $this->assertDatabaseMissing('videos', ['id' => $stalePending->id]);
        $this->assertDatabaseHas('videos', ['id' => $recentPending->id]);
        $this->assertDatabaseHas('videos', ['id' => $ready->id]);

        Carbon::setTestNow();
    }

    public function test_prune_command_is_idempotent_and_keeps_rows_when_storage_delete_fails(): void
    {
        Carbon::setTestNow('2026-07-07 12:00:00');

        $video = Video::factory()->pending()->create([
            'created_at' => now()->subHours(30),
            'updated_at' => now()->subHours(30),
        ]);

        $storage = $this->mockStorageClient();
        $storage->shouldReceive('delete')
            ->twice()
            ->with($video->storage_key)
            ->andThrow(new \RuntimeException('delete failed'));

        Artisan::call('videos:prune-pending');
        $this->assertDatabaseHas('videos', ['id' => $video->id]);

        Artisan::call('videos:prune-pending');
        $this->assertDatabaseHas('videos', ['id' => $video->id]);

        Carbon::setTestNow();
    }

    public function test_upload_url_rejects_mime_types_outside_whitelist(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('videos.upload-url'), $this->uploadUrlPayload([
                'mime_type' => 'video/x-msvideo',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('mime_type');
    }

    public function test_upload_url_accepts_quicktime_mov_mime_type(): void
    {
        $user = User::factory()->create();

        $storage = $this->mockStorageClient();
        $storage->shouldReceive('temporaryUploadUrl')
            ->once()
            ->with(
                Mockery::on(fn (string $key) => (bool) preg_match('/^videos\/'.$user->id.'\/[0-9A-Z]{26}\.mov$/', $key)),
                15,
                'video/quicktime',
            )
            ->andReturn([
                'url' => 'https://example.test/upload',
                'headers' => ['Content-Type' => 'video/quicktime'],
                'expires_at' => now()->addMinutes(15)->toIso8601String(),
            ]);

        $this->actingAs($user)
            ->postJson(route('videos.upload-url'), $this->uploadUrlPayload([
                'mime_type' => 'video/quicktime',
                'title' => 'ブルガリアンスクワット',
            ]))
            ->assertOk()
            ->assertJsonPath('mode', 'single');

        $this->assertDatabaseHas('videos', [
            'user_id' => $user->id,
            'title' => 'ブルガリアンスクワット',
            'mime_type' => 'video/quicktime',
        ]);
    }

    public function test_finalize_accepts_octet_stream_when_declared_mime_is_allowed(): void
    {
        $user = User::factory()->create();
        $video = Video::factory()->pending()->create([
            'user_id' => $user->id,
            'mime_type' => 'video/quicktime',
            'storage_key' => "videos/{$user->id}/01JTESTMOV0000000000000001.mov",
        ]);

        $storage = $this->mockStorageClient();
        $storage->shouldReceive('exists')->once()->with($video->storage_key)->andReturnTrue();
        $storage->shouldReceive('size')->once()->with($video->storage_key)->andReturn(2_000_000);
        $storage->shouldReceive('mimeType')->once()->with($video->storage_key)->andReturn('application/octet-stream');
        $storage->shouldReceive('delete')->never();

        $this->actingAs($user)
            ->postJson(route('videos.finalize', $video))
            ->assertOk()
            ->assertJsonPath('status', VideoStatus::Ready->value);

        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
            'status' => VideoStatus::Ready->value,
            'size_bytes' => 2_000_000,
        ]);
    }

    public function test_delete_removes_pending_videos_physically_and_ready_videos_softly(): void
    {
        $user = User::factory()->create();
        $pending = Video::factory()->pending()->create(['user_id' => $user->id]);
        $ready = Video::factory()->ready()->create(['user_id' => $user->id]);

        $storage = $this->mockStorageClient();
        $storage->shouldReceive('delete')
            ->once()
            ->ordered()
            ->with($pending->storage_key);
        $storage->shouldReceive('delete')
            ->once()
            ->ordered()
            ->with($ready->storage_key);

        $this->actingAs($user)->deleteJson(route('videos.destroy', $pending))->assertOk();
        $this->assertDatabaseMissing('videos', ['id' => $pending->id]);

        $this->actingAs($user)->deleteJson(route('videos.destroy', $ready))->assertOk();
        $this->assertSoftDeleted('videos', ['id' => $ready->id]);
    }

    public function test_json_index_returns_ready_videos_without_inertia(): void
    {
        $user = User::factory()->create();
        Video::factory()->ready()->create(['user_id' => $user->id, 'title' => '準備完了']);
        Video::factory()->pending()->create(['user_id' => $user->id, 'title' => '未完了']);
        Video::factory()->ready()->create(['title' => '他人の動画']);

        $this->actingAs($user)
            ->getJson(route('videos.index'))
            ->assertOk()
            ->assertJsonPath('videos.0.title', '準備完了')
            ->assertJsonCount(1, 'videos')
            ->assertJsonMissingPath('component');
    }
}
