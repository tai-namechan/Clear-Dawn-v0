<?php

namespace Tests\Feature\Kioku;

use App\Domain\Kioku\Jobs\EnrichMemoryJob;
use App\Domain\Kioku\Jobs\TranscribeMemoryAudioJob;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Models\MemoryAsset;
use App\Domain\Kioku\Transcription\OpenAiTranscriptionGateway;
use App\Domain\Kioku\Transcription\TranscriptionGateway;
use App\Domain\Shared\Models\AiUsageLog;
use App\Domain\Shared\Models\AiUsageRequest;
use App\Enums\AiUsageRequestStatus;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class OpenAiTranscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'kioku.audio.disk' => 'local',
            'kioku.transcription.provider' => 'openai',
            'kioku.transcription.model' => 'gpt-4o-mini-transcribe-2025-12-15',
            'kioku.transcription.language' => 'ja',
            'services.openai.key' => 'test-key',
            'services.openai.base_url' => 'https://api.openai.com/v1',
        ]);
        Storage::fake('local');
    }

    /**
     * Build an Http::fake URL pattern from config (no hardcoded hosts).
     */
    private function openAiFakePattern(): string
    {
        $host = parse_url((string) config('services.openai.base_url'), PHP_URL_HOST);

        return ($host ?: 'api.openai.com').'/*';
    }

    /**
     * @return array{memory: Memory, asset: MemoryAsset}
     */
    private function createVoiceMemoryWithAsset(
        User $user,
        string $mimeType = 'audio/wav',
        ?int $durationMs = 9000,
    ): array {
        $memory = Memory::factory()->voice()->create(['user_id' => $user->id]);
        $path = 'kioku-audio/'.$user->id.'/'.$memory->id.'.bin';
        Storage::disk('local')->put($path, 'audio-bytes');
        $asset = MemoryAsset::query()->create([
            'memory_id' => $memory->id,
            'kind' => MemoryAsset::KIND_AUDIO_ORIGINAL,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $mimeType,
            'byte_size' => 11,
            'duration_ms' => $durationMs,
        ]);

        return ['memory' => $memory, 'asset' => $asset];
    }

    private function multipartValue(Request $request, string $name): ?string
    {
        foreach ((array) $request->data() as $part) {
            if (is_array($part) && ($part['name'] ?? null) === $name && is_string($part['contents'] ?? null)) {
                return $part['contents'];
            }
        }

        return null;
    }

    private function transcriptionUsageRequest(): ?AiUsageRequest
    {
        return AiUsageRequest::query()
            ->withoutUserScope()
            ->where('feature', OpenAiTranscriptionGateway::FEATURE)
            ->first();
    }

    public function test_openai_provider_binds_openai_gateway(): void
    {
        $this->assertInstanceOf(OpenAiTranscriptionGateway::class, app(TranscriptionGateway::class));
    }

    public function test_unknown_provider_is_rejected_on_resolution(): void
    {
        config(['kioku.transcription.provider' => 'whisper-x']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown transcription provider [whisper-x]');

        app(TranscriptionGateway::class);
    }

    public function test_missing_api_key_fails_permanently_without_external_call(): void
    {
        config(['services.openai.key' => null]);
        Http::fake();
        Bus::fake([EnrichMemoryJob::class]);
        $user = User::factory()->create();
        ['memory' => $memory, 'asset' => $asset] = $this->createVoiceMemoryWithAsset($user);

        (new TranscribeMemoryAudioJob($memory->id))->handle(app(TranscriptionGateway::class));

        Http::assertNothingSent();
        $memory->refresh();
        $this->assertSame('failed', $memory->transcription_status);
        $this->assertNull($memory->transcript_text);
        Storage::disk('local')->assertExists($asset->path);
        $this->assertNull($this->transcriptionUsageRequest());
        Bus::assertNotDispatched(EnrichMemoryJob::class);
    }

    public function test_successful_transcription_streams_multipart_and_saves_transcript(): void
    {
        Http::fake([
            $this->openAiFakePattern() => Http::response([
                'text' => '会議で決めたことを忘れないうちに残す',
                'usage' => ['type' => 'tokens', 'input_tokens' => 180, 'output_tokens' => 45],
            ]),
        ]);
        Bus::fake([EnrichMemoryJob::class]);
        $user = User::factory()->create();
        ['memory' => $memory] = $this->createVoiceMemoryWithAsset($user);

        (new TranscribeMemoryAudioJob($memory->id))->handle(app(TranscriptionGateway::class));

        $memory->refresh();
        $this->assertSame('会議で決めたことを忘れないうちに残す', $memory->transcript_text);
        $this->assertSame('ready', $memory->transcription_status);
        $this->assertNull($memory->raw_content);
        Bus::assertDispatchedTimes(EnrichMemoryJob::class, 1);

        Http::assertSent(function (Request $request): bool {
            return str_contains($request->url(), '/audio/transcriptions')
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $request->isMultipart()
                && $request->hasFile('file', null, 'audio.wav')
                && $this->multipartValue($request, 'model') === 'gpt-4o-mini-transcribe-2025-12-15'
                && $this->multipartValue($request, 'language') === 'ja'
                && $this->multipartValue($request, 'response_format') === 'json';
        });

        $usageRequest = $this->transcriptionUsageRequest();
        $this->assertNotNull($usageRequest);
        $this->assertSame(AiUsageRequestStatus::Settled, $usageRequest->status);
        $this->assertSame('gpt-4o-mini-transcribe-2025-12-15', $usageRequest->model);
        // actual_usd settles at the real rates ($1.25/1M in, $5/1M out):
        // 180 * 1.25 + 45 * 5 = 450 micro-dollars. The reservation is a
        // deliberately higher estimate and must never be undercut by actual.
        $this->assertSame('0.000450', (string) $usageRequest->actual_usd);
        $this->assertLessThanOrEqual(
            (float) $usageRequest->estimated_usd,
            (float) $usageRequest->actual_usd,
        );

        $log = AiUsageLog::query()
            ->withoutUserScope()
            ->where('feature', OpenAiTranscriptionGateway::FEATURE)
            ->first();
        $this->assertNotNull($log);
        $this->assertSame(180, (int) $log->input_tokens);
        $this->assertSame(45, (int) $log->output_tokens);
    }

    public function test_safe_filename_comes_from_stored_mime_type(): void
    {
        $user = User::factory()->create();

        foreach (['audio/mp4' => 'audio.m4a', 'audio/webm' => 'audio.webm'] as $mime => $filename) {
            Http::fake([
                $this->openAiFakePattern() => Http::response([
                    'text' => 'ok',
                    'usage' => ['type' => 'tokens', 'input_tokens' => 10, 'output_tokens' => 5],
                ]),
            ]);
            ['asset' => $asset] = $this->createVoiceMemoryWithAsset($user, $mime);

            app(TranscriptionGateway::class)->transcribe($asset);

            Http::assertSent(fn (Request $request): bool => $request->hasFile('file', null, $filename));
        }
    }

    public function test_unknown_mime_is_rejected_before_any_provider_call(): void
    {
        Http::fake();
        $user = User::factory()->create();
        ['memory' => $memory, 'asset' => $asset] = $this->createVoiceMemoryWithAsset($user, 'audio/flac');

        (new TranscribeMemoryAudioJob($memory->id))->handle(app(TranscriptionGateway::class));

        Http::assertNothingSent();
        $memory->refresh();
        $this->assertSame('failed', $memory->transcription_status);
        Storage::disk('local')->assertExists($asset->path);
        $this->assertNull($this->transcriptionUsageRequest());
    }

    public function test_unreadable_audio_fails_without_provider_call(): void
    {
        Http::fake();
        $user = User::factory()->create();
        ['memory' => $memory, 'asset' => $asset] = $this->createVoiceMemoryWithAsset($user);
        Storage::disk('local')->delete($asset->path);

        (new TranscribeMemoryAudioJob($memory->id))->handle(app(TranscriptionGateway::class));

        Http::assertNothingSent();
        $this->assertSame('failed', $memory->fresh()->transcription_status);
        $this->assertNull($this->transcriptionUsageRequest());
    }

    public function test_empty_text_is_a_successful_empty_transcript(): void
    {
        Http::fake([
            $this->openAiFakePattern() => Http::response([
                'text' => " \n",
                'usage' => ['type' => 'tokens', 'input_tokens' => 12, 'output_tokens' => 0],
            ]),
        ]);
        Bus::fake([EnrichMemoryJob::class]);
        $user = User::factory()->create();
        ['memory' => $memory] = $this->createVoiceMemoryWithAsset($user);

        (new TranscribeMemoryAudioJob($memory->id))->handle(app(TranscriptionGateway::class));

        $memory->refresh();
        $this->assertSame('ready', $memory->transcription_status);
        $this->assertSame('', $memory->transcript_text);
        $this->assertSame(
            AiUsageRequestStatus::Settled,
            $this->transcriptionUsageRequest()?->status,
        );
    }

    public function test_response_without_text_is_a_failure_and_keeps_reservation_in_flight(): void
    {
        Http::fake([
            $this->openAiFakePattern() => Http::response(['unexpected' => true]),
        ]);
        Bus::fake([EnrichMemoryJob::class]);
        $user = User::factory()->create();
        ['memory' => $memory, 'asset' => $asset] = $this->createVoiceMemoryWithAsset($user);

        $job = new TranscribeMemoryAudioJob($memory->id);
        $job->tries = 1;
        $job->handle(app(TranscriptionGateway::class));

        $memory->refresh();
        $this->assertSame('failed', $memory->transcription_status);
        $this->assertNull($memory->transcript_text);
        Storage::disk('local')->assertExists($asset->path);
        Bus::assertNotDispatched(EnrichMemoryJob::class);
        // Billing happened but is unverifiable: the reaper expires it later.
        $this->assertSame(
            AiUsageRequestStatus::InFlight,
            $this->transcriptionUsageRequest()?->status,
        );
    }

    public function test_auth_and_validation_errors_are_permanent(): void
    {
        foreach ([401, 403, 422] as $status) {
            Http::fake([
                $this->openAiFakePattern() => Http::response(['error' => ['message' => 'rejected']], $status),
            ]);
            $user = User::factory()->create();
            ['memory' => $memory, 'asset' => $asset] = $this->createVoiceMemoryWithAsset($user);

            // tries stays at the default 3: a permanent rejection must not retry.
            (new TranscribeMemoryAudioJob($memory->id))->handle(app(TranscriptionGateway::class));

            $memory->refresh();
            $this->assertSame('failed', $memory->transcription_status, "HTTP {$status}");
            Storage::disk('local')->assertExists($asset->path);

            $usageRequest = AiUsageRequest::query()
                ->withoutUserScope()
                ->where('user_id', $user->id)
                ->sole();
            $this->assertSame(AiUsageRequestStatus::Released, $usageRequest->status, "HTTP {$status}");
        }
    }

    public function test_rate_limit_and_server_errors_are_transient(): void
    {
        foreach ([429, 500, 503] as $status) {
            Http::fake([
                $this->openAiFakePattern() => Http::response(['error' => ['message' => 'busy']], $status),
            ]);
            $user = User::factory()->create();
            ['memory' => $memory, 'asset' => $asset] = $this->createVoiceMemoryWithAsset($user);

            $caught = null;

            try {
                (new TranscribeMemoryAudioJob($memory->id))->handle(app(TranscriptionGateway::class));
            } catch (RuntimeException $e) {
                $caught = $e;
            }

            $this->assertNotNull($caught, "HTTP {$status} should rethrow for the queue retry");
            $memory->refresh();
            // The claim is released so the queued retry can pick it up again.
            $this->assertSame('pending', $memory->transcription_status, "HTTP {$status}");
            $this->assertNull($memory->transcript_text);
            Storage::disk('local')->assertExists($asset->path);

            $usageRequest = AiUsageRequest::query()
                ->withoutUserScope()
                ->where('user_id', $user->id)
                ->sole();
            $this->assertSame(AiUsageRequestStatus::Released, $usageRequest->status, "HTTP {$status}");
        }
    }

    public function test_audio_stream_is_closed_on_success_and_on_failure(): void
    {
        $user = User::factory()->create();
        $recorder = $this->recordReadStreams('local');

        Http::fake([
            $this->openAiFakePattern() => Http::response([
                'text' => 'ok',
                'usage' => ['type' => 'tokens', 'input_tokens' => 10, 'output_tokens' => 5],
            ]),
        ]);
        ['asset' => $asset] = $this->createVoiceMemoryWithAsset($user);
        app(TranscriptionGateway::class)->transcribe($asset);
        $this->assertNotNull($recorder->lastReadStream);
        $this->assertFalse(is_resource($recorder->lastReadStream), 'stream must be closed after success');

        Http::fake([
            $this->openAiFakePattern() => Http::response([], 500),
        ]);
        ['asset' => $asset] = $this->createVoiceMemoryWithAsset($user);
        $recorder->lastReadStream = null;

        try {
            app(TranscriptionGateway::class)->transcribe($asset);
            $this->fail('A 500 response should throw.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertNotNull($recorder->lastReadStream);
        $this->assertFalse(is_resource($recorder->lastReadStream), 'stream must be closed after failure');
    }

    /**
     * Wrap the faked disk so the test can observe the exact resource the
     * gateway opened via readStream().
     *
     * @return FilesystemAdapter&object{lastReadStream: resource|null}
     */
    private function recordReadStreams(string $disk): FilesystemAdapter
    {
        $original = Storage::disk($disk);
        assert($original instanceof FilesystemAdapter);

        $recorder = new class($original->getDriver(), $original->getAdapter(), $original->getConfig()) extends FilesystemAdapter
        {
            /** @var resource|null */
            public $lastReadStream = null;

            public function readStream($path)
            {
                return $this->lastReadStream = parent::readStream($path);
            }
        };

        Storage::set($disk, $recorder);

        return $recorder;
    }

    public function test_duration_usage_type_settles_with_zero_token_log(): void
    {
        Http::fake([
            $this->openAiFakePattern() => Http::response([
                'text' => '短い音声メモ',
                'usage' => ['type' => 'duration', 'seconds' => 9],
            ]),
        ]);
        Bus::fake([EnrichMemoryJob::class]);
        $user = User::factory()->create();
        ['memory' => $memory] = $this->createVoiceMemoryWithAsset($user);

        (new TranscribeMemoryAudioJob($memory->id))->handle(app(TranscriptionGateway::class));

        $this->assertSame('ready', $memory->fresh()->transcription_status);

        $usageRequest = $this->transcriptionUsageRequest();
        $this->assertSame(AiUsageRequestStatus::Settled, $usageRequest?->status);
        $this->assertGreaterThan(0.0, (float) $usageRequest->actual_usd);

        $log = AiUsageLog::query()
            ->withoutUserScope()
            ->where('feature', OpenAiTranscriptionGateway::FEATURE)
            ->sole();
        $this->assertSame(0, (int) $log->input_tokens);
        $this->assertSame(0, (int) $log->output_tokens);
    }

    public function test_missing_usage_settles_at_the_reserved_estimate(): void
    {
        Http::fake([
            $this->openAiFakePattern() => Http::response(['text' => 'usageなしの応答']),
        ]);
        Bus::fake([EnrichMemoryJob::class]);
        $user = User::factory()->create();
        ['memory' => $memory] = $this->createVoiceMemoryWithAsset($user);

        (new TranscribeMemoryAudioJob($memory->id))->handle(app(TranscriptionGateway::class));

        $this->assertSame('ready', $memory->fresh()->transcription_status);

        $usageRequest = $this->transcriptionUsageRequest();
        $this->assertSame(AiUsageRequestStatus::Settled, $usageRequest?->status);
        $this->assertSame((string) $usageRequest->estimated_usd, (string) $usageRequest->actual_usd);
    }

    public function test_exhausted_quota_prevents_any_external_call(): void
    {
        config(['ai.limits.monthly_usd_per_user' => '0.000001']);
        Http::fake();
        $user = User::factory()->create();
        ['memory' => $memory, 'asset' => $asset] = $this->createVoiceMemoryWithAsset($user);

        $job = new TranscribeMemoryAudioJob($memory->id);
        $job->tries = 1;
        $job->handle(app(TranscriptionGateway::class));

        Http::assertNothingSent();
        $this->assertSame('failed', $memory->fresh()->transcription_status);
        Storage::disk('local')->assertExists($asset->path);
    }
}
