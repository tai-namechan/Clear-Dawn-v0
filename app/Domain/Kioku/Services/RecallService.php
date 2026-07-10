<?php

namespace App\Domain\Kioku\Services;

use App\Domain\Kioku\Models\Memory;
use Illuminate\Support\Collection;

/**
 * Past-only recall for AI context injection (Yoyu / Clear Dawn).
 */
final class RecallService
{
    public function __construct(
        private KiokuSearchService $search,
    ) {}

    /**
     * @return list<string>
     */
    public function for(int $userId, string $contextText, int $k = 5, bool $countReference = true): array
    {
        /** @var Collection<int, Memory> $memories */
        $memories = $this->search->search($userId, $contextText, [], max($k * 3, 15))
            ->filter(fn (Memory $memory) => ! $memory->sensitive && $memory->status === 'ready')
            ->take($k)
            ->values();

        if ($countReference) {
            foreach ($memories as $memory) {
                $memory->increment('referenced_count');
            }
        }

        return $memories
            ->map(function (Memory $memory): string {
                $when = $memory->captured_at?->diffForHumans() ?? '';
                $type = $memory->memory_type ?? 'memory';
                $text = $memory->summary ?: mb_substr($memory->raw_content, 0, 200);

                return "[{$when}/{$type}] {$text}";
            })
            ->all();
    }
}
