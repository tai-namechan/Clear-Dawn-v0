<?php

namespace App\Domain\Yoyu\Jobs;

use App\Domain\Connectors\Calendar\CalendarConnectionStatus;
use App\Domain\Shared\AI\AiGateway;
use App\Domain\Shared\AI\QuotaExceededException;
use App\Domain\Yoyu\Data\BriefingContext;
use App\Domain\Yoyu\Exceptions\InvalidBriefingResponseException;
use App\Domain\Yoyu\Models\YoyuBriefing;
use App\Domain\Yoyu\Services\BriefingContextBuilder;
use App\Domain\Yoyu\Services\BriefingPromptBuilder;
use App\Domain\Yoyu\Services\BriefingResponseParser;
use App\Domain\Yoyu\Services\BriefingStructuredDataFactory;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateYoyuBriefingJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 8;

    /**
     * One AI call at up to 60s HTTP timeout, plus DB work / sync_pending waits.
     */
    public int $timeout = 90;

    /**
     * Short delays while waiting for first calendar sync (≈60s total).
     *
     * @var list<int>
     */
    public array $backoff = [5, 5, 10, 10, 10, 10, 15];

    /**
     * Cover queue wait + all retries + Worker stop/resume in Phase 1.
     * (~8 × 90s timeout + backoff + idle) — 2h TTL; generation_id still guards stale writes.
     */
    public int $uniqueFor = 7200;

    /**
     * @param  string  $briefingId  Target briefing row
     * @param  string  $briefingDate  Y-m-d fixed at dispatch (user-local day)
     * @param  string  $timezone  IANA timezone fixed at dispatch
     * @param  string  $generationId  Generation token fixed at dispatch (stale-write guard)
     */
    public function __construct(
        public string $briefingId,
        public string $briefingDate,
        public string $timezone,
        public string $generationId,
    ) {}

    public function uniqueId(): string
    {
        // Per-generation uniqueness: a finishing job's unique lock must not
        // silently drop a newly dispatched regenerate for the same briefing.
        return $this->briefingId.':'.$this->generationId;
    }

    public function handle(
        AiGateway $ai,
        BriefingContextBuilder $contexts,
        BriefingPromptBuilder $prompts,
        BriefingResponseParser $parser,
        BriefingStructuredDataFactory $factory,
    ): void {
        $briefing = YoyuBriefing::query()->withoutUserScope()->find($this->briefingId);
        if ($briefing === null) {
            return;
        }

        if ((string) $briefing->generation_id !== $this->generationId) {
            Log::info('GenerateYoyuBriefingJob stale generation; skipping', [
                'briefing_id' => $this->briefingId,
                'job_generation_id' => $this->generationId,
            ]);

            return;
        }

        $user = User::query()->find($briefing->user_id);
        if ($user === null) {
            return;
        }

        $rowDate = $briefing->date->toDateString();
        if ($rowDate !== $this->briefingDate) {
            Log::warning('GenerateYoyuBriefingJob date mismatch; refusing to rewrite', [
                'briefing_id' => $this->briefingId,
                'row_date' => $rowDate,
                'payload_date' => $this->briefingDate,
                'timezone' => $this->timezone,
            ]);

            return;
        }

        // Mark generating without clearing body / structured_data (same generation only).
        $this->touchGenerating($briefing);

        $context = $contexts->build($user, $this->briefingDate, $this->timezone);

        if (
            $context->calendar->connectionStatus === CalendarConnectionStatus::Syncing
            || $context->calendar->warningCode === 'sync_pending'
        ) {
            if ($context->calendar->syncedAt === null && $context->calendar->events === []) {
                if ($this->attempts() < 7) {
                    $this->updateIfCurrentGeneration(['status' => 'pending']);
                    $this->release($this->backoff[$this->attempts() - 1] ?? 10);

                    return;
                }
            }
        }

        $built = $prompts->build($context);

        $apiKey = config('ai.anthropic.api_key');
        if (! is_string($apiKey) || $apiKey === '') {
            $structured = $factory->make($context, $this->emptyGeneration('invalid_response'));
            $this->persistSuccess(
                $briefing,
                $this->preferExistingBody($briefing, $factory->bodyFromStructured($structured)),
                $structured,
            );

            return;
        }

        try {
            $result = $ai->complete(
                userId: (int) $briefing->user_id,
                feature: 'yoyu.briefing',
                prompt: $built['prompt'],
                tier: 'cheap',
                maxTokens: 900,
                messages: [
                    ['role' => 'user', 'content' => $built['prompt']->variableSuffix],
                ],
            );

            try {
                $parsed = $parser->parse($result['text'], $built['allowlist']);
            } catch (InvalidBriefingResponseException) {
                // Provider usage already settled inside AiGateway — never release here.
                // Do not log raw AI text.
                Log::warning('GenerateYoyuBriefingJob invalid AI response', [
                    'briefing_id' => $this->briefingId,
                    'briefing_date' => $this->briefingDate,
                    'timezone' => $this->timezone,
                    'error_code' => 'invalid_response',
                ]);

                $structured = $factory->make($context, $this->emptyGeneration('invalid_response'));
                $this->persistSuccess(
                    $briefing,
                    $this->preferExistingBody($briefing, $factory->bodyFromStructured($structured)),
                    $structured,
                );

                return;
            }

            $generation = array_merge(['status' => 'generated'], $parsed);
            $structured = $factory->make($context, $generation);
            $body = $factory->bodyFromStructured($structured);
            $this->persistSuccess($briefing, $body, $structured);
        } catch (QuotaExceededException) {
            $structured = $factory->make($context, $this->emptyGeneration('quota_limited'));
            $this->persistSuccess(
                $briefing,
                $this->preferExistingBody($briefing, $factory->bodyFromStructured($structured)),
                $structured,
            );
        } catch (Throwable $e) {
            Log::warning('GenerateYoyuBriefingJob failed', [
                'briefing_id' => $this->briefingId,
                'briefing_date' => $this->briefingDate,
                'timezone' => $this->timezone,
                'error_code' => 'transient_failure',
            ]);

            if ($this->attempts() >= $this->tries) {
                $this->persistRetryExhaustion($briefing, $factory, $context);

                return;
            }

            $this->updateIfCurrentGeneration(['status' => 'pending']);

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        YoyuBriefing::query()
            ->withoutUserScope()
            ->whereKey($this->briefingId)
            ->where('generation_id', $this->generationId)
            ->whereIn('status', ['pending', 'generating'])
            ->update(['status' => 'failed']);
    }

    /**
     * @param  array<string, mixed>  $structured
     */
    private function persistSuccess(YoyuBriefing $briefing, string $body, array $structured): void
    {
        DB::transaction(function () use ($body, $structured): void {
            $updated = $this->updateIfCurrentGeneration([
                'body' => $body,
                'structured_data' => $structured,
                'status' => 'ready',
            ]);

            if ($updated === 0) {
                Log::info('GenerateYoyuBriefingJob skipped stale write', [
                    'briefing_id' => $this->briefingId,
                    'job_generation_id' => $this->generationId,
                ]);
            }
        });
    }

    private function persistRetryExhaustion(
        YoyuBriefing $briefing,
        BriefingStructuredDataFactory $factory,
        BriefingContext $context,
    ): void {
        $briefing->refresh();
        if ((string) $briefing->generation_id !== $this->generationId) {
            return;
        }

        $hasPrior = is_array($briefing->structured_data)
            && (($briefing->structured_data['schema_version'] ?? null) === BriefingStructuredDataFactory::SCHEMA_VERSION);

        if ($hasPrior) {
            $this->updateIfCurrentGeneration(['status' => 'failed']);

            return;
        }

        $structured = $factory->make($context, $this->emptyGeneration('invalid_response'));
        $this->updateIfCurrentGeneration([
            'body' => $this->preferExistingBody($briefing, $factory->bodyFromStructured($structured)),
            'structured_data' => $structured,
            'status' => 'failed',
        ]);
    }

    private function touchGenerating(YoyuBriefing $briefing): void
    {
        $this->updateIfCurrentGeneration(['status' => 'generating']);
        $briefing->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function updateIfCurrentGeneration(array $attributes): int
    {
        return YoyuBriefing::query()
            ->withoutUserScope()
            ->whereKey($this->briefingId)
            ->where('generation_id', $this->generationId)
            ->update($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyGeneration(string $status): array
    {
        return [
            'status' => $status,
            'overview' => null,
            'caution' => null,
            'hand_note' => null,
            'gap_suggestions' => [],
            'let_go' => null,
            'pattern_note' => null,
        ];
    }

    private function preferExistingBody(YoyuBriefing $briefing, string $fallback): string
    {
        $existing = trim((string) $briefing->body);
        if ($existing !== '' && ! str_contains($existing, '生成しています')) {
            return $existing;
        }

        return $fallback;
    }
}
