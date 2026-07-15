<?php

namespace App\Domain\Kioku\Transcription;

use App\Domain\Kioku\Models\MemoryAsset;
use App\Domain\Shared\AI\AiCostCalculator;
use App\Domain\Shared\AI\AiMoney;
use App\Domain\Shared\AI\AiUsageLedger;
use App\Domain\Shared\Models\AiUsageRequest;
use App\Enums\AiUsageRequestStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * OpenAI Audio Transcriptions adapter
 * (docs/product/kioku-final-remaining-implementation.md §2–4).
 *
 * Streams the stored audio original from the private disk as a multipart
 * upload (never base64) and bills the existing AiUsage ledger with the same
 * reserve → in_flight → settle/release lifecycle as AiGateway. Permanent
 * failures throw TranscriptionFailedException so the job never re-bills a
 * hopeless request; transient failures throw RuntimeException and are left
 * to the job's bounded retries. An empty transcript is a success.
 */
final class OpenAiTranscriptionGateway implements TranscriptionGateway
{
    public const FEATURE = 'kioku.transcription';

    /**
     * Server-detected MIME → upload filename extension. Unknown MIME types
     * are rejected before any provider call or ledger reservation.
     */
    private const MIME_EXTENSIONS = [
        'audio/webm' => 'webm',
        'video/webm' => 'webm',

        'audio/mp4' => 'm4a',
        'video/mp4' => 'mp4',
        'audio/x-m4a' => 'm4a',

        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',

        'audio/ogg' => 'ogg',
        'application/ogg' => 'ogg',

        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/vnd.wave' => 'wav',
    ];

    /**
     * OpenAI's ~$0.003/min estimate at the $1.25/1M audio input rate implies
     * ~40 audio tokens/second. estimated_usd (the ledger reservation) may be
     * safely high, so it reserves at 50/s plus a flat output allowance;
     * actual_usd always settles from real usage at the config/ai.php rates.
     */
    private const ESTIMATED_AUDIO_TOKENS_PER_SECOND = 50;

    private const ESTIMATED_OUTPUT_TOKENS = 1500;

    /**
     * Duration-type usage carries no token counts; 40 tokens/second matches
     * OpenAI's ~$0.003/min estimate at the configured input rate.
     */
    private const DURATION_AUDIO_TOKENS_PER_SECOND = 40;

    public function __construct(
        private AiUsageLedger $ledger,
        private AiCostCalculator $costs,
    ) {}

    public function transcribe(MemoryAsset $asset): TranscriptionResult
    {
        $apiKey = config('services.openai.key');
        if (! is_string($apiKey) || $apiKey === '') {
            throw new TranscriptionFailedException('OpenAI API key is not configured (OPENAI_API_KEY).');
        }

        $model = (string) config('kioku.transcription.model', '');
        if ($model === '') {
            throw new TranscriptionFailedException('Transcription model is not configured (KIOKU_TRANSCRIPTION_MODEL).');
        }

        $extension = self::MIME_EXTENSIONS[$asset->mime_type] ?? null;
        if ($extension === null) {
            throw new TranscriptionFailedException(
                "Unsupported audio MIME type [{$asset->mime_type}] for transcription."
            );
        }

        $userId = (int) $asset->memory()->withoutUserScope()->value('user_id');
        if ($userId <= 0) {
            throw new TranscriptionFailedException('Audio asset is not attached to an owned memory.');
        }

        $stream = $this->openAudioStream($asset);

        try {
            return $this->requestTranscription($apiKey, $model, $asset, $stream, $extension, $userId);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * @param  resource  $stream
     */
    private function requestTranscription(
        string $apiKey,
        string $model,
        MemoryAsset $asset,
        $stream,
        string $extension,
        int $userId,
    ): TranscriptionResult {
        $estimated = $this->estimateReservation($model, $asset);
        // May throw QuotaExceededException: no HTTP has happened, so the
        // job's normal bounded retry (or eventual failure) is the right path.
        $usageRequest = $this->ledger->reserve($userId, self::FEATURE, $model, $estimated);
        $httpAttempted = false;

        try {
            $inFlight = $this->ledger->markInFlight($usageRequest->id);
            if ($inFlight->status !== AiUsageRequestStatus::InFlight) {
                throw new RuntimeException(
                    "AI usage request [{$usageRequest->id}] is not in_flight; refusing provider HTTP."
                );
            }
            // Only after a confirmed in_flight reservation may HTTP begin.
            $httpAttempted = true;

            $response = Http::withToken($apiKey)
                ->baseUrl((string) config('services.openai.base_url'))
                ->connectTimeout(10)
                ->timeout((int) config('kioku.transcription.timeout_seconds', 120))
                ->attach('file', $stream, 'audio.'.$extension, [
                    'Content-Type' => $asset->mime_type,
                ])
                ->post('/audio/transcriptions', [
                    'model' => $model,
                    'language' => (string) config('kioku.transcription.language', 'ja'),
                    'response_format' => 'json',
                ]);
        } catch (Throwable $e) {
            if (! $httpAttempted) {
                $this->safeRelease($usageRequest, 'pre_http_failure');
            }
            // After an ambiguous send (timeout / connection loss) the
            // reservation stays in_flight for the reaper, same as AiGateway.
            if ($e instanceof ConnectionException) {
                throw new RuntimeException('OpenAI transcription connection failed.', 0, $e);
            }

            throw $e;
        }

        if ($response->failed()) {
            // An HTTP error response means the transcription was not billed.
            $this->safeRelease($usageRequest, 'provider_http_error');
            $status = $response->status();

            if ($this->isPermanentStatus($status)) {
                throw new TranscriptionFailedException(
                    "OpenAI transcription request was rejected (HTTP {$status})."
                );
            }

            throw new RuntimeException("OpenAI transcription request failed (HTTP {$status}).");
        }

        $text = $this->extractText($response, $usageRequest, $userId);

        $this->settleUsage($usageRequest, $model, $response->json('usage'), $estimated, $asset);

        return new TranscriptionResult(text: $text, provider: 'openai', model: $model);
    }

    private function extractText(Response $response, AiUsageRequest $usageRequest, int $userId): string
    {
        $data = $response->json();

        if (! is_array($data) || ! is_string($data['text'] ?? null)) {
            // HTTP 200 with an unusable body: billing likely happened but is
            // unverifiable, so leave the reservation in_flight for the reaper
            // to expire at the estimate. Never log the raw response body.
            Log::warning('OpenAI transcription response is missing text.', [
                'usage_request_id' => $usageRequest->id,
                'user_id' => $userId,
                'http_status' => $response->status(),
            ]);

            throw new RuntimeException('OpenAI transcription response is missing text.');
        }

        // Whitespace-only speech is a successful empty transcript
        // (transcription_status=ready + the existing empty_ready UI).
        return trim($data['text']);
    }

    /**
     * Settle the reservation from whichever usage shape the provider sent:
     * token-type ({input_tokens, output_tokens}), duration-type
     * ({type: "duration", seconds}), or none at all. A settle problem never
     * fails the transcription itself — the transcript exists and was paid
     * for; the reaper expires anything left in_flight.
     */
    private function settleUsage(
        AiUsageRequest $usageRequest,
        string $model,
        mixed $usage,
        AiMoney $estimated,
        MemoryAsset $asset,
    ): void {
        try {
            $tokens = $this->parseTokenUsage($usage);
            $durationSeconds = $this->parseDurationUsage($usage);

            if ($tokens !== null) {
                [$inputTokens, $outputTokens] = $tokens;
                $actual = $this->costs->actualCost($model, $inputTokens, $outputTokens);
                $this->ledger->settle($usageRequest->id, $actual, $inputTokens, $outputTokens);
            } elseif ($durationSeconds !== null) {
                // Bill the audio seconds at the per-minute-equivalent token
                // rate and keep the log's token columns at zero (unknown).
                $actual = $this->costs->actualCost(
                    $model,
                    $durationSeconds * self::DURATION_AUDIO_TOKENS_PER_SECOND,
                    0,
                );
                $this->ledger->settle($usageRequest->id, $actual, 0, 0);

                Log::info('Kioku transcription settled from duration usage.', [
                    'usage_request_id' => $usageRequest->id,
                    'memory_id' => $asset->memory_id,
                    'audio_seconds' => $durationSeconds,
                ]);
            } else {
                // No usable usage: settle at the reserved estimate so spend
                // stays conservative and the request still reaches a
                // terminal, auditable state.
                Log::warning('OpenAI transcription response missing usable usage; settling at estimate.', [
                    'usage_request_id' => $usageRequest->id,
                    'memory_id' => $asset->memory_id,
                ]);
                $this->ledger->settle($usageRequest->id, $estimated, 0, 0);
            }
        } catch (Throwable $e) {
            Log::warning('Failed to settle transcription usage.', [
                'usage_request_id' => $usageRequest->id,
                'memory_id' => $asset->memory_id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function parseTokenUsage(mixed $usage): ?array
    {
        if (! is_array($usage)) {
            return null;
        }

        if (($usage['type'] ?? 'tokens') !== 'tokens') {
            return null;
        }

        $input = $usage['input_tokens'] ?? null;
        $output = $usage['output_tokens'] ?? 0;

        if (! $this->isNonNegativeInt($input) || ! $this->isNonNegativeInt($output)) {
            return null;
        }

        return [(int) $input, (int) $output];
    }

    private function parseDurationUsage(mixed $usage): ?int
    {
        if (! is_array($usage) || ($usage['type'] ?? null) !== 'duration') {
            return null;
        }

        $seconds = $usage['seconds'] ?? null;
        if (! is_int($seconds) && ! is_float($seconds) && ! (is_string($seconds) && is_numeric($seconds))) {
            return null;
        }

        $seconds = (float) $seconds;

        return $seconds >= 0 ? (int) ceil($seconds) : null;
    }

    private function isNonNegativeInt(mixed $value): bool
    {
        if (is_int($value)) {
            return $value >= 0;
        }

        return is_string($value) && preg_match('/^\d+$/', $value) === 1;
    }

    /**
     * @return resource
     */
    private function openAudioStream(MemoryAsset $asset)
    {
        try {
            $stream = Storage::disk($asset->disk)->readStream($asset->path);
        } catch (Throwable $e) {
            throw new TranscriptionFailedException(
                'Could not read the audio original from storage.', 0, $e,
            );
        }

        if (! is_resource($stream)) {
            throw new TranscriptionFailedException('Could not read the audio original from storage.');
        }

        return $stream;
    }

    private function estimateReservation(string $model, MemoryAsset $asset): AiMoney
    {
        $durationSeconds = (int) ceil(((int) ($asset->duration_ms ?? 0)) / 1000);
        if ($durationSeconds < 1) {
            // Unknown duration: reserve for the configured maximum recording.
            $durationSeconds = (int) ceil(((int) config('kioku.audio.max_duration_ms', 180_000)) / 1000);
        }

        $rates = $this->costs->ratesFor($model);

        return AiMoney::estimateFromTokensAndRates(
            $durationSeconds * self::ESTIMATED_AUDIO_TOKENS_PER_SECOND,
            self::ESTIMATED_OUTPUT_TOKENS,
            $rates['input'],
            $rates['output'],
        );
    }

    /**
     * 4xx statuses that will not change on retry. 408/409/429 stay transient;
     * every 5xx is transient.
     */
    private function isPermanentStatus(int $status): bool
    {
        if ($status < 400 || $status >= 500) {
            return false;
        }

        return ! in_array($status, [408, 409, 429], true);
    }

    private function safeRelease(AiUsageRequest $usageRequest, string $failureCode): void
    {
        try {
            $this->ledger->release($usageRequest->id, $failureCode);
        } catch (Throwable $e) {
            Log::warning('Failed to release transcription usage reservation.', [
                'usage_request_id' => $usageRequest->id,
                'failure_code' => $failureCode,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
