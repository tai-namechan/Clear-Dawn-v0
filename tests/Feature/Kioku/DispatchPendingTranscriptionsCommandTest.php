<?php

namespace Tests\Feature\Kioku;

use App\Domain\Kioku\Jobs\TranscribeMemoryAudioJob;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Models\MemoryAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DispatchPendingTranscriptionsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['kioku.audio.disk' => 'local']);
        Storage::fake('local');
    }

    /**
     * @return array{memory: Memory, asset: MemoryAsset}
     */
    private function createPendingVoice(User $user, string $path = 'pending.wav'): array
    {
        $memory = Memory::factory()->voice()->create(['user_id' => $user->id]);
        Storage::disk('local')->put('kioku-audio/'.$user->id.'/'.$path, 'audio-bytes');
        $asset = MemoryAsset::query()->create([
            'memory_id' => $memory->id,
            'kind' => MemoryAsset::KIND_AUDIO_ORIGINAL,
            'disk' => 'local',
            'path' => 'kioku-audio/'.$user->id.'/'.$path,
            'mime_type' => 'audio/wav',
            'byte_size' => 11,
            'duration_ms' => 9000,
        ]);

        return ['memory' => $memory, 'asset' => $asset];
    }

    public function test_command_fails_without_transcription_provider(): void
    {
        config(['kioku.transcription.provider' => 'none']);
        Bus::fake([TranscribeMemoryAudioJob::class]);
        $user = User::factory()->create();
        $this->createPendingVoice($user);

        $this->artisan('kioku:transcriptions:dispatch-pending')
            ->assertFailed();

        Bus::assertNotDispatched(TranscribeMemoryAudioJob::class);
    }

    public function test_command_dispatches_only_pending_voice_with_audio_asset(): void
    {
        config(['kioku.transcription.provider' => 'fake']);
        Bus::fake([TranscribeMemoryAudioJob::class]);
        $user = User::factory()->create();

        ['memory' => $pending] = $this->createPendingVoice($user, 'a.wav');

        $ready = Memory::factory()->voice()->create([
            'user_id' => $user->id,
            'transcription_status' => 'ready',
            'transcript_text' => 'done',
        ]);
        MemoryAsset::query()->create([
            'memory_id' => $ready->id,
            'kind' => MemoryAsset::KIND_AUDIO_ORIGINAL,
            'disk' => 'local',
            'path' => 'kioku-audio/'.$user->id.'/ready.wav',
            'mime_type' => 'audio/wav',
            'byte_size' => 1,
            'duration_ms' => 1000,
        ]);
        Storage::disk('local')->put('kioku-audio/'.$user->id.'/ready.wav', 'x');

        $processing = Memory::factory()->voice()->create([
            'user_id' => $user->id,
            'transcription_status' => 'processing',
        ]);
        MemoryAsset::query()->create([
            'memory_id' => $processing->id,
            'kind' => MemoryAsset::KIND_AUDIO_ORIGINAL,
            'disk' => 'local',
            'path' => 'kioku-audio/'.$user->id.'/processing.wav',
            'mime_type' => 'audio/wav',
            'byte_size' => 1,
            'duration_ms' => 1000,
        ]);

        $failed = Memory::factory()->voice()->create([
            'user_id' => $user->id,
            'transcription_status' => 'failed',
            'status' => 'failed',
        ]);
        MemoryAsset::query()->create([
            'memory_id' => $failed->id,
            'kind' => MemoryAsset::KIND_AUDIO_ORIGINAL,
            'disk' => 'local',
            'path' => 'kioku-audio/'.$user->id.'/failed.wav',
            'mime_type' => 'audio/wav',
            'byte_size' => 1,
            'duration_ms' => 1000,
        ]);

        Memory::factory()->create(['user_id' => $user->id]); // manual

        $orphanPending = Memory::factory()->voice()->create(['user_id' => $user->id]);

        $this->artisan('kioku:transcriptions:dispatch-pending')
            ->assertSuccessful();

        Bus::assertDispatchedTimes(TranscribeMemoryAudioJob::class, 1);
        Bus::assertDispatched(
            TranscribeMemoryAudioJob::class,
            fn (TranscribeMemoryAudioJob $job) => $job->memoryId === $pending->id,
        );
    }

    public function test_command_rerun_relies_on_unique_job_without_duplicate_queue_rows(): void
    {
        config([
            'queue.default' => 'database',
            'kioku.transcription.provider' => 'fake',
        ]);
        $user = User::factory()->create();
        $this->createPendingVoice($user);

        $this->artisan('kioku:transcriptions:dispatch-pending')->assertSuccessful();
        $this->artisan('kioku:transcriptions:dispatch-pending')->assertSuccessful();

        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_dry_run_does_not_dispatch(): void
    {
        config(['kioku.transcription.provider' => 'fake']);
        Bus::fake([TranscribeMemoryAudioJob::class]);
        $user = User::factory()->create();
        $this->createPendingVoice($user);

        $this->artisan('kioku:transcriptions:dispatch-pending', ['--dry-run' => true])
            ->assertSuccessful();

        Bus::assertNotDispatched(TranscribeMemoryAudioJob::class);
    }

    public function test_command_dispatches_backfill_with_openai_provider(): void
    {
        config(['kioku.transcription.provider' => 'openai']);
        Bus::fake([TranscribeMemoryAudioJob::class]);
        $user = User::factory()->create();
        ['memory' => $pending] = $this->createPendingVoice($user);

        $this->artisan('kioku:transcriptions:dispatch-pending')
            ->assertSuccessful();

        Bus::assertDispatched(
            TranscribeMemoryAudioJob::class,
            fn (TranscribeMemoryAudioJob $job) => $job->memoryId === $pending->id,
        );
    }
}
