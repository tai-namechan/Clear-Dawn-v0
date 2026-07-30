<?php

namespace Tests\Feature\Kioku;

use App\Domain\Kioku\Capture\CaptureChannel;
use App\Domain\Kioku\Jobs\EnrichMemoryJob;
use App\Domain\Kioku\Jobs\TranscribeMemoryAudioJob;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Models\MemoryAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AudioFileImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'kioku.audio.disk' => 'local',
            'kioku.audio_import.enabled' => true,
            'kioku.audio_import.max_bytes' => 24 * 1024 * 1024,
            'kioku.transcription.provider' => 'none',
        ]);
        Storage::fake('local');
    }

    private function fakeWavFile(int $dataBytes = 2048, string $name = 'import.wav'): UploadedFile
    {
        $header = 'RIFF'.pack('V', 36 + $dataBytes).'WAVE'
            .'fmt '.pack('V', 16).pack('v', 1).pack('v', 1)
            .pack('V', 8000).pack('V', 8000).pack('v', 1).pack('v', 8)
            .'data'.pack('V', $dataBytes);

        return UploadedFile::fake()->createWithContent($name, $header.str_repeat("\x00", $dataBytes));
    }

    public function test_guests_cannot_import_audio(): void
    {
        $this->postJson(route('kioku.captures.audio-import'), [
            'client_capture_id' => (string) Str::uuid(),
            'audio' => $this->fakeWavFile(),
        ])->assertUnauthorized();
    }

    public function test_flag_off_forbids_import(): void
    {
        config(['kioku.audio_import.enabled' => false]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('kioku.captures.audio-import'), [
                'client_capture_id' => (string) Str::uuid(),
                'audio' => $this->fakeWavFile(),
            ])
            ->assertForbidden();
    }

    public function test_wav_import_stores_private_asset_and_sets_channel(): void
    {
        Bus::fake([EnrichMemoryJob::class, TranscribeMemoryAudioJob::class]);
        $user = User::factory()->create();
        $captureId = (string) Str::uuid();

        $this->actingAs($user)
            ->postJson(route('kioku.captures.audio-import'), [
                'client_capture_id' => $captureId,
                'audio' => $this->fakeWavFile(),
                'duration_ms' => 5000,
            ])
            ->assertCreated()
            ->assertJsonPath('created', true)
            ->assertJsonPath('memory.source_type', 'voice');

        $memory = Memory::query()->withoutUserScope()->where('user_id', $user->id)->sole();
        $this->assertSame('audio', $memory->raw_kind);
        $this->assertSame(CaptureChannel::AudioFileImport->value, $memory->capture_channel);
        $this->assertNull($memory->raw_content);
        $this->assertSame('pending', $memory->transcription_status);

        $asset = MemoryAsset::query()->where('memory_id', $memory->id)->sole();
        $this->assertSame('audio_original', $asset->kind);
        Storage::disk('local')->assertExists($asset->path);
        Bus::assertNotDispatched(TranscribeMemoryAudioJob::class);
    }

    public function test_import_dispatches_transcription_when_provider_configured(): void
    {
        config(['kioku.transcription.provider' => 'fake']);
        Bus::fake([EnrichMemoryJob::class, TranscribeMemoryAudioJob::class]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('kioku.captures.audio-import'), [
                'client_capture_id' => (string) Str::uuid(),
                'audio' => $this->fakeWavFile(),
            ])
            ->assertCreated();

        Bus::assertDispatched(TranscribeMemoryAudioJob::class);
    }

    public function test_duplicate_client_capture_id_is_idempotent(): void
    {
        $user = User::factory()->create();
        $captureId = (string) Str::uuid();

        $first = $this->actingAs($user)
            ->postJson(route('kioku.captures.audio-import'), [
                'client_capture_id' => $captureId,
                'audio' => $this->fakeWavFile(),
            ])
            ->assertCreated()
            ->json('memory.id');

        $this->actingAs($user)
            ->postJson(route('kioku.captures.audio-import'), [
                'client_capture_id' => $captureId,
                'audio' => $this->fakeWavFile(4096, 'again.wav'),
            ])
            ->assertOk()
            ->assertJsonPath('created', false)
            ->assertJsonPath('memory.id', $first);

        $this->assertSame(1, Memory::query()->withoutUserScope()->where('user_id', $user->id)->count());
        $this->assertSame(1, MemoryAsset::query()->count());
    }

    public function test_unsupported_mime_is_rejected(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('notes.txt', 'not audio');

        $this->actingAs($user)
            ->postJson(route('kioku.captures.audio-import'), [
                'client_capture_id' => (string) Str::uuid(),
                'audio' => $file,
            ])
            ->assertUnprocessable();
    }

    public function test_oversize_file_is_rejected(): void
    {
        config(['kioku.audio_import.max_bytes' => 1024]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('kioku.captures.audio-import'), [
                'client_capture_id' => (string) Str::uuid(),
                'audio' => $this->fakeWavFile(4096),
            ])
            ->assertUnprocessable();
    }

    public function test_other_user_cannot_play_imported_audio(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $memoryId = $this->actingAs($owner)
            ->postJson(route('kioku.captures.audio-import'), [
                'client_capture_id' => (string) Str::uuid(),
                'audio' => $this->fakeWavFile(),
            ])
            ->assertCreated()
            ->json('memory.id');

        $this->actingAs($other)
            ->get(route('kioku.memories.audio', $memoryId))
            ->assertNotFound();
    }

    public function test_server_probes_wav_duration_and_ignores_client_placeholder(): void
    {
        Bus::fake([EnrichMemoryJob::class, TranscribeMemoryAudioJob::class]);
        $user = User::factory()->create();

        // 2048 bytes @ 8kHz 8-bit mono ≈ 256ms
        $this->actingAs($user)
            ->postJson(route('kioku.captures.audio-import'), [
                'client_capture_id' => (string) Str::uuid(),
                'audio' => $this->fakeWavFile(2048),
                'duration_ms' => 1,
            ])
            ->assertCreated();

        $asset = MemoryAsset::query()->sole();
        $this->assertNotSame(1, $asset->duration_ms);
        $this->assertGreaterThanOrEqual(200, (int) $asset->duration_ms);
        $this->assertLessThanOrEqual(400, (int) $asset->duration_ms);
    }

    public function test_omitted_duration_still_imports_with_probed_length(): void
    {
        Bus::fake([EnrichMemoryJob::class, TranscribeMemoryAudioJob::class]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('kioku.captures.audio-import'), [
                'client_capture_id' => (string) Str::uuid(),
                'audio' => $this->fakeWavFile(4096),
            ])
            ->assertCreated();

        $asset = MemoryAsset::query()->sole();
        $this->assertNotNull($asset->duration_ms);
        $this->assertGreaterThan(1, (int) $asset->duration_ms);
    }

    public function test_probed_duration_over_import_max_is_rejected(): void
    {
        config(['kioku.audio_import.max_duration_ms' => 1000]);
        Bus::fake([EnrichMemoryJob::class, TranscribeMemoryAudioJob::class]);
        $user = User::factory()->create();

        // 16_000 bytes @ 8kHz 8-bit mono = 2 seconds > 1s max
        $this->actingAs($user)
            ->postJson(route('kioku.captures.audio-import'), [
                'client_capture_id' => (string) Str::uuid(),
                'audio' => $this->fakeWavFile(16_000),
                'duration_ms' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['audio']);

        $this->assertSame(0, Memory::query()->withoutUserScope()->where('user_id', $user->id)->count());
    }
}
