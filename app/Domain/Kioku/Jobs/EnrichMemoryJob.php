<?php

namespace App\Domain\Kioku\Jobs;

use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Services\MemoryClassifier;
use App\Domain\Kioku\Services\RelatedMemoryService;
use App\Domain\Kioku\Types\MemoryTypeRegistry;
use App\Domain\Shared\AI\AiGateway;
use App\Domain\Shared\AI\PromptTemplate;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class EnrichMemoryJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Two AI calls at up to 60s HTTP timeout each, plus DB work.
     */
    public int $timeout = 180;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 90];

    public int $uniqueFor = 3600;

    public function __construct(public string $memoryId) {}

    public function uniqueId(): string
    {
        return $this->memoryId;
    }

    public function handle(
        AiGateway $ai,
        MemoryTypeRegistry $registry,
        MemoryClassifier $classifier,
        RelatedMemoryService $relatedMemoryService,
    ): void {
        $memory = Memory::query()->withoutUserScope()->find($this->memoryId);
        if ($memory === null) {
            return;
        }

        if (in_array($memory->status, ['ready', 'failed', 'archived'], true)) {
            return;
        }

        // Voice memories are enriched from the transcript (derived data).
        // Without one there is nothing to analyze yet — transcription will
        // dispatch this job again once the transcript is ready.
        $content = $memory->enrichmentSourceText();
        if ($content === null || trim($content) === '') {
            return;
        }

        if (! $this->claim($memory)) {
            return;
        }

        // No DB transaction is held here: each update below is autocommit so
        // the external AI calls never run inside an open transaction.
        try {
            $this->classify($ai, $registry, $classifier, $memory, $content);

            $type = $registry->get((string) $memory->memory_type);
            $tier = in_array($memory->memory_type, ['error_log', 'decision'], true) ? 'strong' : 'cheap';

            $extractPrompt = PromptTemplate::make(
                'extract.'.$memory->memory_type.'.v1',
                'You extract structured memory fields. Reply with JSON only.',
                $type->extractionPrompt($content),
            );

            $extracted = $ai->complete(
                userId: (int) $memory->user_id,
                feature: 'kioku.extract',
                prompt: $extractPrompt,
                tier: $tier,
                maxTokens: 900,
            );

            $payload = $this->decodeJson($extracted['text']);

            $memory->update([
                'summary' => isset($payload['summary']) ? (string) $payload['summary'] : null,
                'structured_data' => is_array($payload['structured_data'] ?? null) ? $payload['structured_data'] : null,
                'status' => 'ready',
            ]);

            $relatedMemoryService->cacheRelated($memory);
        } catch (Throwable $e) {
            Log::warning('EnrichMemoryJob failed', [
                'memory_id' => $this->memoryId,
                'attempt' => $this->attempts(),
                'message' => $e->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $memory->update(['status' => 'failed']);

                return;
            }

            // Release the claim so the retry can pick it up. Classification
            // already persisted on the memory is kept, so the classify AI
            // call is not billed again on retry.
            $memory->update(['status' => 'captured']);

            throw $e;
        }
    }

    /**
     * Safety net for timeouts / killed workers where handle() never reaches
     * its catch block: mark the memory failed while keeping raw_content.
     */
    public function failed(?Throwable $exception): void
    {
        Memory::query()
            ->withoutUserScope()
            ->whereKey($this->memoryId)
            ->whereIn('status', ['captured', 'enriching'])
            ->update(['status' => 'failed']);
    }

    /**
     * Atomically claim the memory so only one worker enriches it.
     * First attempt only claims freshly captured memories; retries may also
     * reclaim 'enriching' left behind by a timed-out earlier attempt.
     */
    private function claim(Memory $memory): bool
    {
        $claimable = $this->attempts() > 1 ? ['captured', 'enriching'] : ['captured'];

        $claimed = Memory::query()
            ->withoutUserScope()
            ->whereKey($memory->id)
            ->whereIn('status', $claimable)
            ->update(['status' => 'enriching']);

        if ($claimed === 1) {
            $memory->status = 'enriching';

            return true;
        }

        return false;
    }

    /**
     * Classify the memory, persisting the result immediately so a later
     * extract failure never re-bills the classify call. A retry that finds
     * memory_type already set skips the AI call entirely.
     */
    private function classify(
        AiGateway $ai,
        MemoryTypeRegistry $registry,
        MemoryClassifier $classifier,
        Memory $memory,
        string $content,
    ): void {
        if ($memory->memory_type !== null && in_array($memory->memory_type, $registry->keys(), true)) {
            return;
        }

        $classification = $classifier->classify($ai, (int) $memory->user_id, $content);

        $memory->update([
            'memory_type' => $classification['memory_type'],
            'title' => $classification['title'] ?? mb_substr($content, 0, 40),
            'tags' => $classification['tags'],
            'importance' => $classification['importance'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $text): array
    {
        $trimmed = trim($text);
        if (preg_match('/\{.*\}/s', $trimmed, $matches) === 1) {
            $trimmed = $matches[0];
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : [];
    }
}
