<?php

namespace App\Domain\Kioku\Jobs;

use App\Domain\Kioku\Models\Memory;
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
        RelatedMemoryService $relatedMemoryService,
    ): void {
        $memory = Memory::query()->withoutUserScope()->find($this->memoryId);
        if ($memory === null) {
            return;
        }

        if (in_array($memory->status, ['ready', 'failed', 'archived'], true)) {
            return;
        }

        if (! $this->claim($memory)) {
            return;
        }

        // No DB transaction is held here: each update below is autocommit so
        // the external AI calls never run inside an open transaction.
        try {
            $this->classify($ai, $registry, $memory);

            $type = $registry->get((string) $memory->memory_type);
            $tier = in_array($memory->memory_type, ['error_log', 'decision'], true) ? 'strong' : 'cheap';

            $extractPrompt = PromptTemplate::make(
                'extract.'.$memory->memory_type.'.v1',
                'You extract structured memory fields. Reply with JSON only.',
                $type->extractionPrompt($memory->raw_content),
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
    private function classify(AiGateway $ai, MemoryTypeRegistry $registry, Memory $memory): void
    {
        if ($memory->memory_type !== null && in_array($memory->memory_type, $registry->keys(), true)) {
            return;
        }

        $classifyPrompt = PromptTemplate::make(
            'classify.v1',
            'You classify personal memories. Reply with JSON only.',
            "Classify this memory. Return JSON: {\"memory_type\":\"one of thought,emotion,decision,learning,error_log,idea,reference,event,conversation\",\"importance\":1-5,\"tags\":[\"...\"],\"title\":\"short title\"}\n\n".$memory->raw_content,
        );

        $classified = $ai->complete(
            userId: (int) $memory->user_id,
            feature: 'kioku.classify',
            prompt: $classifyPrompt,
            tier: 'cheap',
            maxTokens: 400,
        );

        $classification = $this->decodeJson($classified['text']);
        $memoryType = (string) ($classification['memory_type'] ?? 'thought');
        if (! in_array($memoryType, $registry->keys(), true)) {
            $memoryType = 'thought';
        }

        $memory->update([
            'memory_type' => $memoryType,
            'title' => (string) ($classification['title'] ?? mb_substr($memory->raw_content, 0, 40)),
            'tags' => is_array($classification['tags'] ?? null) ? array_values($classification['tags']) : [],
            'importance' => max(1, min(5, (int) ($classification['importance'] ?? 3))),
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
