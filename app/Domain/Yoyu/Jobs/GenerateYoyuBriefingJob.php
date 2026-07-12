<?php

namespace App\Domain\Yoyu\Jobs;

use App\Domain\Connectors\Calendar\CalendarConnectionStatus;
use App\Domain\Shared\AI\AiGateway;
use App\Domain\Shared\AI\QuotaExceededException;
use App\Domain\Yoyu\Data\BriefingContext;
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

    public int $uniqueFor = 600;

    /**
     * @param  string  $briefingId  Target briefing row
     * @param  string  $briefingDate  Y-m-d fixed at dispatch (user-local day)
     * @param  string  $timezone  IANA timezone fixed at dispatch
     */
    public function __construct(
        public string $briefingId,
        public string $briefingDate,
        public string $timezone,
    ) {}

    public function uniqueId(): string
    {
        return $this->briefingId;
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

        // Mark generating without clearing body / structured_data.
        $briefing->update(['status' => 'generating']);

        $context = $contexts->build($user, $this->briefingDate, $this->timezone);

        // Connected but never synced: wait briefly for SyncCalendarJob (no Google HTTP here).
        if (
            $context->calendar->connectionStatus === CalendarConnectionStatus::Syncing
            || $context->calendar->warningCode === 'sync_pending'
        ) {
            if ($context->calendar->syncedAt === null && $context->calendar->events === []) {
                if ($this->attempts() < 7) {
                    $briefing->update(['status' => 'pending']);
                    $this->release($this->backoff[$this->attempts() - 1] ?? 10);

                    return;
                }
                // Fall through: empty schedule + sync_pending warning.
            }
        }

        $built = $prompts->build($context);

        $apiKey = config('ai.anthropic.api_key');
        if (! is_string($apiKey) || $apiKey === '') {
            $structured = $factory->make($context, [
                'status' => 'invalid_response',
                'overview' => null,
                'caution' => null,
                'hand_note' => null,
                'gap_suggestions' => [],
                'let_go' => null,
                'pattern_note' => null,
            ]);
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
                // Put instructions in `system`, JSON data only in the user message.
                messages: [
                    ['role' => 'user', 'content' => $built['prompt']->variableSuffix],
                ],
            );

            try {
                $parsed = $parser->parse($result['text'], $built['allowlist']);
                $generation = array_merge(['status' => 'generated'], $parsed);
                $structured = $factory->make($context, $generation);
                $body = $factory->bodyFromStructured($structured);

                $this->persistSuccess($briefing, $body, $structured);
            } catch (Throwable $parseError) {
                // Provider usage already settled inside AiGateway — never release here.
                Log::warning('GenerateYoyuBriefingJob invalid AI response', [
                    'briefing_id' => $this->briefingId,
                    'briefing_date' => $this->briefingDate,
                    'timezone' => $this->timezone,
                    'error_code' => 'invalid_response',
                ]);

                $structured = $factory->make($context, [
                    'status' => 'invalid_response',
                    'overview' => null,
                    'caution' => null,
                    'hand_note' => null,
                    'gap_suggestions' => [],
                    'let_go' => null,
                    'pattern_note' => null,
                ]);
                $body = $this->preferExistingBody(
                    $briefing,
                    $factory->bodyFromStructured($structured),
                );
                $this->persistSuccess($briefing, $body, $structured);
            }
        } catch (QuotaExceededException) {
            $structured = $factory->make($context, [
                'status' => 'quota_limited',
                'overview' => null,
                'caution' => null,
                'hand_note' => null,
                'gap_suggestions' => [],
                'let_go' => null,
                'pattern_note' => null,
            ]);
            $body = $this->preferExistingBody(
                $briefing,
                $factory->bodyFromStructured($structured),
            );
            $this->persistSuccess($briefing, $body, $structured);
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

            // Keep old body/structured_data; only flip status for polling.
            $briefing->update(['status' => 'pending']);

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        YoyuBriefing::query()
            ->withoutUserScope()
            ->whereKey($this->briefingId)
            ->whereIn('status', ['pending', 'generating'])
            ->update(['status' => 'failed']);
    }

    /**
     * @param  array<string, mixed>  $structured
     */
    private function persistSuccess(YoyuBriefing $briefing, string $body, array $structured): void
    {
        DB::transaction(function () use ($briefing, $body, $structured): void {
            $briefing->refresh();
            $briefing->update([
                'body' => $body,
                'structured_data' => $structured,
                'status' => 'ready',
            ]);
        });
    }

    private function persistRetryExhaustion(
        YoyuBriefing $briefing,
        BriefingStructuredDataFactory $factory,
        BriefingContext $context,
    ): void {
        // Do not wipe existing body/structured_data. If none yet, leave analysis fallback.
        $briefing->refresh();
        $hasPrior = is_array($briefing->structured_data)
            && (($briefing->structured_data['schema_version'] ?? null) === BriefingStructuredDataFactory::SCHEMA_VERSION);

        if ($hasPrior) {
            $briefing->update(['status' => 'failed']);

            return;
        }

        $structured = $factory->make($context, [
            'status' => 'invalid_response',
            'overview' => null,
            'caution' => null,
            'hand_note' => null,
            'gap_suggestions' => [],
            'let_go' => null,
            'pattern_note' => null,
        ]);

        $briefing->update([
            'body' => $this->preferExistingBody($briefing, $factory->bodyFromStructured($structured)),
            'structured_data' => $structured,
            'status' => 'failed',
        ]);
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
