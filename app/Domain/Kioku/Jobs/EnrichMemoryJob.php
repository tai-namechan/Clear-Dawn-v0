<?php

namespace App\Domain\Kioku\Jobs;

use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Services\RelatedMemoryService;
use App\Domain\Kioku\Types\MemoryTypeRegistry;
use App\Domain\Shared\AI\AiGateway;
use App\Domain\Shared\AI\PromptTemplate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class EnrichMemoryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 30, 90];

    public function __construct(public string $memoryId) {}

    public function handle(
        AiGateway $ai,
        MemoryTypeRegistry $registry,
        RelatedMemoryService $relatedMemoryService,
    ): void {
        $memory = Memory::query()->withoutUserScope()->find($this->memoryId);
        if ($memory === null) {
            return;
        }

        $memory->update(['status' => 'enriching']);

        try {
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

            $type = $registry->get($memoryType);
            $tier = in_array($memoryType, ['error_log', 'decision'], true) ? 'strong' : 'cheap';

            $extractPrompt = PromptTemplate::make(
                'extract.'.$memoryType.'.v1',
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
                'memory_type' => $memoryType,
                'title' => (string) ($classification['title'] ?? mb_substr($memory->raw_content, 0, 40)),
                'summary' => isset($payload['summary']) ? (string) $payload['summary'] : null,
                'structured_data' => is_array($payload['structured_data'] ?? null) ? $payload['structured_data'] : null,
                'tags' => is_array($classification['tags'] ?? null) ? array_values($classification['tags']) : [],
                'importance' => max(1, min(5, (int) ($classification['importance'] ?? 3))),
                'status' => 'ready',
            ]);

            $relatedMemoryService->cacheRelated($memory);
        } catch (Throwable $e) {
            Log::warning('EnrichMemoryJob failed', [
                'memory_id' => $this->memoryId,
                'message' => $e->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $memory->update(['status' => 'failed']);

                return;
            }

            $memory->update(['status' => 'captured']);

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        Memory::query()
            ->withoutUserScope()
            ->whereKey($this->memoryId)
            ->whereIn('status', ['captured', 'enriching'])
            ->update(['status' => 'failed']);
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
