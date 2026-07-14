<?php

namespace Tests\Feature\Kioku;

use App\Domain\Kioku\Jobs\EnrichMemoryJob;
use App\Domain\Kioku\Jobs\TranscribeMemoryAudioJob;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Models\MemoryAsset;
use App\Domain\Kioku\Services\KiokuSearchService;
use App\Domain\Kioku\Services\MemoryClassifier;
use App\Domain\Kioku\Services\RelatedMemoryService;
use App\Domain\Kioku\Transcription\TranscriptionGateway;
use App\Domain\Kioku\Transcription\TranscriptionResult;
use App\Domain\Kioku\Types\MemoryTypeRegistry;
use App\Domain\Shared\AI\AiGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class TranscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['kioku.audio.disk' => 'local']);
        Storage::fake('local');
    }

    private function fakeWavFile(int $dataBytes = 2048): UploadedFile
    {
        $header = 'RIFF'.pack('V', 36 + $dataBytes).'WAVE'
            .'fmt '.pack('V', 16).pack('v', 1).pack('v', 1)
            .pack('V', 8000).pack('V', 8000).pack('v', 1).pack('v', 8)
            .'data'.pack('V', $dataBytes);

        return UploadedFile::fake()->createWithContent('voice.wav', $header.str_repeat("\x00", $dataBytes));
    }

    /**
     * @return array{memory: Memory, asset: MemoryAsset}
     */
    private function createVoiceMemoryWithAsset(User $user): array
    {
        $memory = Memory::factory()->voice()->create(['user_id' => $user->id]);
        Storage::disk('local')->put('kioku-audio/'.$user->id.'/test.wav', 'audio-bytes');
        $asset = MemoryAsset::query()->create([
            'memory_id' => $memory->id,
            'kind' => MemoryAsset::KIND_AUDIO_ORIGINAL,
            'disk' => 'local',
            'path' => 'kioku-audio/'.$user->id.'/test.wav',
            'mime_type' => 'audio/wav',
            'byte_size' => 11,
            'duration_ms' => 9000,
        ]);

        return ['memory' => $memory, 'asset' => $asset];
    }

    public function test_voice_capture_dispatches_transcription_when_provider_configured(): void
    {
        config(['kioku.transcription.provider' => 'fake']);
        Bus::fake([TranscribeMemoryAudioJob::class, EnrichMemoryJob::class]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('kioku.captures.voice'), [
                'client_capture_id' => (string) Str::uuid(),
                'audio' => $this->fakeWavFile(),
                'duration_ms' => 9000,
            ])
            ->assertCreated();

        Bus::assertDispatched(TranscribeMemoryAudioJob::class);
        Bus::assertNotDispatched(EnrichMemoryJob::class);
    }

    public function test_voice_capture_without_provider_does_not_dispatch_and_stays_pending(): void
    {
        config(['kioku.transcription.provider' => 'none']);
        Bus::fake([TranscribeMemoryAudioJob::class]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('kioku.captures.voice'), [
                'client_capture_id' => (string) Str::uuid(),
                'audio' => $this->fakeWavFile(),
                'duration_ms' => 9000,
            ])
            ->assertCreated()
            ->assertJsonPath('memory.transcription_status', 'pending');

        Bus::assertNotDispatched(TranscribeMemoryAudioJob::class);
    }

    public function test_transcription_job_saves_transcript_and_chains_enrichment(): void
    {
        config([
            'kioku.transcription.provider' => 'fake',
            'kioku.transcription.fake_text' => '会議で決めたことを忘れないうちに残す',
        ]);
        Bus::fake([EnrichMemoryJob::class]);
        $user = User::factory()->create();
        ['memory' => $memory] = $this->createVoiceMemoryWithAsset($user);

        (new TranscribeMemoryAudioJob($memory->id))->handle(app(TranscriptionGateway::class));

        $memory->refresh();
        $this->assertSame('会議で決めたことを忘れないうちに残す', $memory->transcript_text);
        $this->assertSame('ready', $memory->transcription_status);
        $this->assertNull($memory->raw_content);
        $this->assertSame('captured', $memory->status);
        Bus::assertDispatched(EnrichMemoryJob::class);
    }

    public function test_transcription_failure_keeps_audio_asset_and_marks_failed(): void
    {
        config(['kioku.transcription.provider' => 'fake']);
        $this->app->bind(TranscriptionGateway::class, fn () => new class implements TranscriptionGateway
        {
            public function transcribe(MemoryAsset $asset): TranscriptionResult
            {
                throw new RuntimeException('provider down');
            }
        });
        Bus::fake([EnrichMemoryJob::class]);
        $user = User::factory()->create();
        ['memory' => $memory, 'asset' => $asset] = $this->createVoiceMemoryWithAsset($user);

        $job = new TranscribeMemoryAudioJob($memory->id);
        $job->tries = 1;
        $job->handle(app(TranscriptionGateway::class));

        $memory->refresh();
        $this->assertSame('failed', $memory->transcription_status);
        $this->assertSame('failed', $memory->status);
        $this->assertNull($memory->transcript_text);
        $this->assertSame(1, MemoryAsset::query()->count());
        Storage::disk('local')->assertExists($asset->path);
        Bus::assertNotDispatched(EnrichMemoryJob::class);
    }

    public function test_duplicate_transcription_dispatch_queues_only_one_job(): void
    {
        config([
            'queue.default' => 'database',
            'kioku.transcription.provider' => 'fake',
        ]);
        $user = User::factory()->create();
        ['memory' => $memory] = $this->createVoiceMemoryWithAsset($user);

        TranscribeMemoryAudioJob::dispatch($memory->id);
        TranscribeMemoryAudioJob::dispatch($memory->id);

        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_processing_memory_is_not_claimed_by_a_first_attempt(): void
    {
        config(['kioku.transcription.provider' => 'fake']);
        $counter = new \stdClass;
        $counter->calls = 0;
        $this->app->bind(TranscriptionGateway::class, function () use ($counter) {
            return new class($counter) implements TranscriptionGateway
            {
                public function __construct(private readonly \stdClass $counter) {}

                public function transcribe(MemoryAsset $asset): TranscriptionResult
                {
                    $this->counter->calls++;

                    return new TranscriptionResult('x', 'fake');
                }
            };
        });
        $user = User::factory()->create();
        ['memory' => $memory] = $this->createVoiceMemoryWithAsset($user);
        Memory::query()->withoutUserScope()->whereKey($memory->id)
            ->update(['transcription_status' => 'processing']);

        (new TranscribeMemoryAudioJob($memory->id))->handle(app(TranscriptionGateway::class));

        $this->assertSame(0, $counter->calls);
        $this->assertSame('processing', $memory->fresh()->transcription_status);
    }

    public function test_ready_transcription_is_not_redone(): void
    {
        config(['kioku.transcription.provider' => 'fake', 'kioku.transcription.fake_text' => '上書きされてはいけない']);
        Bus::fake([EnrichMemoryJob::class]);
        $user = User::factory()->create();
        ['memory' => $memory] = $this->createVoiceMemoryWithAsset($user);
        Memory::query()->withoutUserScope()->whereKey($memory->id)->update([
            'transcript_text' => '確定済みの文字起こし',
            'transcription_status' => 'ready',
        ]);

        (new TranscribeMemoryAudioJob($memory->id))->handle(app(TranscriptionGateway::class));

        $this->assertSame('確定済みの文字起こし', $memory->fresh()->transcript_text);
        Bus::assertNotDispatched(EnrichMemoryJob::class);
    }

    public function test_enrichment_uses_transcript_for_voice_memories(): void
    {
        Http::fake([
            $this->anthropicFakePattern() => Http::sequence()
                ->push([
                    'content' => [[
                        'type' => 'text',
                        'text' => '{"memory_type":"decision","importance":4,"tags":["会議"],"title":"会議の決定"}',
                    ]],
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 20],
                ])
                ->push([
                    'content' => [[
                        'type' => 'text',
                        'text' => '{"summary":"決定の要約","structured_data":null}',
                    ]],
                    'usage' => ['input_tokens' => 11, 'output_tokens' => 30],
                ]),
        ]);
        config(['ai.anthropic.api_key' => 'test-key']);

        $user = User::factory()->create();
        $memory = Memory::factory()->voice()->create([
            'user_id' => $user->id,
            'transcript_text' => '明日からAPIのバージョニング方針をv2に統一すると決めた',
            'transcription_status' => 'ready',
        ]);

        (new EnrichMemoryJob($memory->id))->handle(
            app(AiGateway::class),
            app(MemoryTypeRegistry::class),
            app(MemoryClassifier::class),
            app(RelatedMemoryService::class),
        );

        $memory->refresh();
        $this->assertSame('ready', $memory->status);
        $this->assertSame('decision', $memory->memory_type);
        $this->assertNull($memory->raw_content);

        Http::assertSent(function ($request): bool {
            /** @var array{messages?: list<array{content?: string}>} $payload */
            $payload = json_decode((string) $request->body(), true) ?: [];
            $content = implode(' ', array_column($payload['messages'] ?? [], 'content'));

            return str_contains($content, 'バージョニング方針');
        });
    }

    public function test_enrichment_skips_voice_memory_without_transcript(): void
    {
        Http::fake();
        config(['ai.anthropic.api_key' => 'test-key']);

        $user = User::factory()->create();
        $memory = Memory::factory()->voice()->create(['user_id' => $user->id]);

        (new EnrichMemoryJob($memory->id))->handle(
            app(AiGateway::class),
            app(MemoryTypeRegistry::class),
            app(MemoryClassifier::class),
            app(RelatedMemoryService::class),
        );

        Http::assertNothingSent();
        $memory->refresh();
        $this->assertSame('captured', $memory->status);
        $this->assertSame('pending', $memory->transcription_status);
    }

    public function test_search_matches_transcript_text(): void
    {
        $user = User::factory()->create();
        Memory::factory()->voice()->create([
            'user_id' => $user->id,
            'transcript_text' => 'ヨガの新しいポーズを試した',
            'transcription_status' => 'ready',
            'status' => 'ready',
            'title' => '音声の記憶',
        ]);
        Memory::factory()->create([
            'user_id' => $user->id,
            'title' => '別の記憶',
            'raw_content' => '関係ない内容',
            'status' => 'ready',
        ]);

        $found = app(KiokuSearchService::class)->search((int) $user->id, 'ヨガ');

        $this->assertCount(1, $found);
        $this->assertSame('音声の記憶', $found->first()->title);
    }

    public function test_owner_can_retry_failed_transcription(): void
    {
        config(['kioku.transcription.provider' => 'fake']);
        Bus::fake([TranscribeMemoryAudioJob::class]);
        $user = User::factory()->create();
        $memory = Memory::factory()->voice()->create([
            'user_id' => $user->id,
            'transcription_status' => 'failed',
            'status' => 'failed',
        ]);

        $this->actingAs($user)
            ->post(route('kioku.memories.retry-transcription', $memory))
            ->assertRedirect(route('kioku.memories.show', $memory));

        $memory->refresh();
        $this->assertSame('pending', $memory->transcription_status);
        $this->assertSame('captured', $memory->status);
        Bus::assertDispatched(TranscribeMemoryAudioJob::class);
    }

    public function test_retry_without_provider_does_not_reset_state(): void
    {
        config(['kioku.transcription.provider' => 'none']);
        Bus::fake([TranscribeMemoryAudioJob::class]);
        $user = User::factory()->create();
        $memory = Memory::factory()->voice()->create([
            'user_id' => $user->id,
            'transcription_status' => 'failed',
            'status' => 'failed',
        ]);

        $this->actingAs($user)
            ->post(route('kioku.memories.retry-transcription', $memory))
            ->assertRedirect(route('kioku.memories.show', $memory));

        $this->assertSame('failed', $memory->fresh()->transcription_status);
        Bus::assertNotDispatched(TranscribeMemoryAudioJob::class);
    }

    public function test_other_users_cannot_retry_transcription(): void
    {
        config(['kioku.transcription.provider' => 'fake']);
        $memory = Memory::factory()->voice()->create([
            'user_id' => User::factory()->create()->id,
            'transcription_status' => 'failed',
        ]);

        $this->actingAs(User::factory()->create())
            ->post(route('kioku.memories.retry-transcription', $memory))
            ->assertNotFound();
    }

    public function test_transcription_job_without_provider_leaves_memory_pending(): void
    {
        config(['kioku.transcription.provider' => 'none']);
        Bus::fake([EnrichMemoryJob::class]);
        $user = User::factory()->create();
        ['memory' => $memory] = $this->createVoiceMemoryWithAsset($user);

        (new TranscribeMemoryAudioJob($memory->id))->handle(app(TranscriptionGateway::class));

        $memory->refresh();
        $this->assertSame('pending', $memory->transcription_status);
        $this->assertNull($memory->transcript_text);
        Bus::assertNotDispatched(EnrichMemoryJob::class);
    }
}
