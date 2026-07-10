<?php

namespace App\Domain\Kioku\Services;

use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Models\MemoryLink;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class RelatedMemoryService
{
    /**
     * Compute and cache related memories (kind=related, created_by=system).
     *
     * @return Collection<int, Memory>
     */
    public function cacheRelated(Memory $memory, int $limit = 3): Collection
    {
        $candidates = Memory::query()
            ->withoutUserScope()
            ->where('user_id', $memory->user_id)
            ->where('id', '!=', $memory->id)
            ->where('status', 'ready')
            ->orderByDesc('captured_at')
            ->limit(100)
            ->get();

        $scored = $candidates
            ->map(function (Memory $candidate) use ($memory): array {
                return [
                    'memory' => $candidate,
                    'score' => $this->score($memory, $candidate),
                ];
            })
            ->filter(fn (array $row) => $row['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->values();

        MemoryLink::query()
            ->where('from_memory_id', $memory->id)
            ->where('kind', 'related')
            ->where('created_by', 'system')
            ->delete();

        foreach ($scored as $row) {
            MemoryLink::query()->create([
                'from_memory_id' => $memory->id,
                'to_memory_id' => $row['memory']->id,
                'kind' => 'related',
                'score' => $row['score'],
                'created_by' => 'system',
            ]);
        }

        return $scored->pluck('memory');
    }

    /**
     * @return Collection<int, Memory>
     */
    public function forMemory(Memory $memory, int $limit = 3): Collection
    {
        $links = MemoryLink::query()
            ->where('from_memory_id', $memory->id)
            ->where('kind', 'related')
            ->orderByDesc('score')
            ->limit($limit)
            ->get();

        if ($links->isEmpty()) {
            return $this->cacheRelated($memory, $limit);
        }

        $ids = $links->pluck('to_memory_id')->all();

        return Memory::query()
            ->withoutUserScope()
            ->where('user_id', $memory->user_id)
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Memory $m) => array_search($m->id, $ids, true))
            ->values();
    }

    private function score(Memory $a, Memory $b): float
    {
        $tagsA = collect($a->tags ?? [])->map(fn ($t) => Str::lower((string) $t));
        $tagsB = collect($b->tags ?? [])->map(fn ($t) => Str::lower((string) $t));
        $tagScore = $tagsA->intersect($tagsB)->count() * 2;

        $wordsA = $this->titleWords($a->title);
        $wordsB = $this->titleWords($b->title);
        $titleScore = $wordsA->intersect($wordsB)->count();

        return (float) ($tagScore + $titleScore);
    }

    /**
     * @return Collection<int, string>
     */
    private function titleWords(string $title): Collection
    {
        return collect(preg_split('/[\s　、。,.\/\-_|]+/u', $title) ?: [])
            ->map(fn ($w) => Str::lower(trim((string) $w)))
            ->filter(fn ($w) => mb_strlen($w) >= 2)
            ->values();
    }
}
