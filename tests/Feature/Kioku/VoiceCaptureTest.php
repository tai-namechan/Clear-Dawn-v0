<?php

namespace Tests\Feature\Kioku;

use App\Domain\Kioku\Jobs\EnrichMemoryJob;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Models\MemoryAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class VoiceCaptureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['kioku.audio.disk' => 'local']);
        Storage::fake('local');
    }

    /**
     * Minimal valid WAV so finfo detects a real audio MIME type.
     */
    private function fakeWavFile(int $dataBytes = 2048, string $name = 'voice.wav'): UploadedFile
    {
        $header = 'RIFF'.pack('V', 36 + $dataBytes).'WAVE'
            .'fmt '.pack('V', 16).pack('v', 1).pack('v', 1)
            .pack('V', 8000).pack('V', 8000).pack('v', 1).pack('v', 8)
            .'data'.pack('V', $dataBytes);

        return UploadedFile::fake()->createWithContent($name, $header.str_repeat("\x00", $dataBytes));
    }

    public function test_guests_cannot_upload_voice_captures(): void
    {
        $this->postJson(route('kioku.captures.voice'), [
            'client_capture_id' => (string) Str::uuid(),
            'audio' => $this->fakeWavFile(),
            'duration_ms' => 12000,
        ])->assertUnauthorized();
    }

    public function test_voice_capture_stores_private_audio_and_memory(): void
    {
        Bus::fake([EnrichMemoryJob::class]);
        $user = User::factory()->create();
        $captureId = (string) Str::uuid();

        $this->actingAs($user)
            ->postJson(route('kioku.captures.voice'), [
                'client_capture_id' => $captureId,
                'audio' => $this->fakeWavFile(),
                'duration_ms' => 12000,
                'captured_at' => '2026-07-12T14:00:00Z',
            ])
            ->assertCreated()
            ->assertJsonPath('created', true)
            ->assertJsonPath('memory.source_type', 'voice')
            ->assertJsonPath('memory.raw_content', null)
            ->assertJsonPath('memory.transcription_status', 'pending');

        $memory = Memory::query()->withoutUserScope()->where('user_id', $user->id)->sole();
        $this->assertNull($memory->raw_content);
        $this->assertSame('captured', $memory->status);

        $asset = MemoryAsset::query()->where('memory_id', $memory->id)->sole();
        $this->assertSame('audio_original', $asset->kind);
        $this->assertSame(12000, $asset->duration_ms);
        $this->assertNotNull($asset->checksum);
        Storage::disk('local')->assertExists($asset->path);
        $this->assertStringStartsWith('kioku-audio/'.$user->id.'/', $asset->path);

        // Without a transcript there is nothing to enrich yet.
        Bus::assertNotDispatched(EnrichMemoryJob::class);
    }

    public function test_voice_capture_is_idempotent_and_leaves_no_orphan_file(): void
    {
        $user = User::factory()->create();
        $captureId = (string) Str::uuid();

        $first = $this->actingAs($user)
            ->postJson(route('kioku.captures.voice'), [
                'client_capture_id' => $captureId,
                'audio' => $this->fakeWavFile(),
                'duration_ms' => 9000,
            ])
            ->assertCreated()
            ->json('memory.id');

        $this->actingAs($user)
            ->postJson(route('kioku.captures.voice'), [
                'client_capture_id' => $captureId,
                'audio' => $this->fakeWavFile(),
                'duration_ms' => 9000,
            ])
            ->assertOk()
            ->assertJsonPath('created', false)
            ->assertJsonPath('memory.id', $first);

        $this->assertSame(1, Memory::query()->withoutUserScope()->where('user_id', $user->id)->count());
        $this->assertSame(1, MemoryAsset::query()->count());
        $this->assertCount(1, Storage::disk('local')->allFiles('kioku-audio/'.$user->id));
    }

    public function test_non_audio_file_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('kioku.captures.voice'), [
                'client_capture_id' => (string) Str::uuid(),
                'audio' => UploadedFile::fake()->createWithContent('note.txt', 'ただのテキスト'),
                'duration_ms' => 5000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['audio']);

        $this->assertSame(0, Memory::query()->withoutUserScope()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_oversized_audio_is_rejected(): void
    {
        config(['kioku.audio.max_bytes' => 1024 * 1024]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('kioku.captures.voice'), [
                'client_capture_id' => (string) Str::uuid(),
                'audio' => $this->fakeWavFile(2 * 1024 * 1024),
                'duration_ms' => 5000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['audio']);
    }

    public function test_declared_duration_ms_over_three_minutes_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('kioku.captures.voice'), [
                'client_capture_id' => (string) Str::uuid(),
                'audio' => $this->fakeWavFile(),
                'duration_ms' => 181_000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['duration_ms']);
    }

    public function test_voice_memory_detail_is_reachable_while_transcription_pending(): void
    {
        config(['kioku.transcription.provider' => 'none']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('kioku.captures.voice'), [
                'client_capture_id' => (string) Str::uuid(),
                'audio' => $this->fakeWavFile(),
                'duration_ms' => 8000,
            ])
            ->assertCreated();

        $memory = Memory::query()->withoutUserScope()->where('user_id', $user->id)->sole();
        $this->assertSame('captured', $memory->status);
        $this->assertSame('pending', $memory->transcription_status);

        $this->actingAs($user)
            ->get(route('kioku.memories.show', $memory))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Kioku/Detail')
                ->where('memory.id', $memory->id)
                ->where('memory.source_type', 'voice')
                ->where('memory.transcription_status', 'pending')
                ->where('transcriptionEnabled', false));

        $this->actingAs($user)
            ->get(route('kioku.memories.audio', $memory))
            ->assertOk();
    }

    public function test_owner_can_stream_audio(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('kioku.captures.voice'), [
                'client_capture_id' => (string) Str::uuid(),
                'audio' => $this->fakeWavFile(),
                'duration_ms' => 8000,
            ])
            ->assertCreated();

        $memory = Memory::query()->withoutUserScope()->where('user_id', $user->id)->sole();

        $response = $this->actingAs($user)->get(route('kioku.memories.audio', $memory));
        $response->assertOk();
        $this->assertStringContainsString('audio', (string) $response->headers->get('Content-Type'));
    }

    public function test_other_users_cannot_stream_audio(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->postJson(route('kioku.captures.voice'), [
                'client_capture_id' => (string) Str::uuid(),
                'audio' => $this->fakeWavFile(),
                'duration_ms' => 8000,
            ])
            ->assertCreated();

        $memory = Memory::query()->withoutUserScope()->where('user_id', $owner->id)->sole();

        $this->actingAs(User::factory()->create())
            ->get(route('kioku.memories.audio', $memory))
            ->assertNotFound();
    }

    public function test_memory_without_audio_returns_404_for_stream(): void
    {
        $user = User::factory()->create();
        $memory = Memory::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('kioku.memories.audio', $memory))
            ->assertNotFound();
    }

    /**
     * Proves DB asset + missing storage object returns 404, not 500.
     * Storage::response() would otherwise throw on size() for a gone path.
     */
    public function test_missing_audio_file_returns_404_not_500(): void
    {
        $user = User::factory()->create();
        $memory = Memory::factory()->voice()->create(['user_id' => $user->id]);

        MemoryAsset::query()->create([
            'memory_id' => $memory->id,
            'kind' => MemoryAsset::KIND_AUDIO_ORIGINAL,
            'disk' => 'local',
            'path' => 'kioku-audio/'.$user->id.'/gone.wav',
            'mime_type' => 'audio/wav',
            'byte_size' => 100,
            'duration_ms' => 1000,
        ]);

        $this->actingAs($user)
            ->get(route('kioku.memories.audio', $memory))
            ->assertNotFound();
    }

    public function test_deleting_memory_removes_asset_and_file(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('kioku.captures.voice'), [
                'client_capture_id' => (string) Str::uuid(),
                'audio' => $this->fakeWavFile(),
                'duration_ms' => 8000,
            ])
            ->assertCreated();

        $memory = Memory::query()->withoutUserScope()->where('user_id', $user->id)->sole();
        $asset = MemoryAsset::query()->where('memory_id', $memory->id)->sole();

        $memory->delete();

        $this->assertSame(0, MemoryAsset::query()->count());
        Storage::disk('local')->assertMissing($asset->path);
    }
}
