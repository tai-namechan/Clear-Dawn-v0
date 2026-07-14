<?php

namespace App\Http\Resources\Kioku;

use App\Domain\Kioku\Models\KiokuLetter;
use App\Domain\Kioku\Models\KiokuLetterItem;
use App\Domain\Kioku\Models\Memory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin KiokuLetter
 */
class KiokuLetterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var KiokuLetter $letter */
        $letter = $this->resource;
        $items = $letter->items;
        $relatedTitles = $this->relatedTitles($letter);

        return [
            'id' => $letter->id,
            'week_start' => $letter->week_start->toDateString(),
            'week_end' => $letter->week_start->addDays(6)->toDateString(),
            'status' => $letter->status,
            'character_variant' => $letter->character_variant,
            'intro' => $letter->intro,
            'item_count' => $letter->item_count,
            'published_at' => $letter->published_at?->toIso8601String(),
            'opened_at' => $letter->opened_at?->toIso8601String(),
            'completed_at' => $letter->completed_at?->toIso8601String(),
            'evaluation_memory_id' => $letter->evaluation_memory_id,
            'items' => $items->map(fn (KiokuLetterItem $item): array => [
                'id' => $item->id,
                'position' => $item->position,
                'memory_id' => $item->memory_id,
                'title' => $item->title_snapshot,
                'summary' => $item->summary_snapshot,
                'headline' => $item->headline,
                'why_now' => $item->why_now,
                'related' => collect($item->related_memory_ids ?? [])
                    ->map(fn (string $id): array => [
                        'id' => $id,
                        'title' => $relatedTitles[$id] ?? null,
                    ])
                    ->values()
                    ->all(),
                'verdict' => $item->verdict,
                'verdict_note' => $item->verdict_note,
                'verdict_at' => $item->verdict_at?->toIso8601String(),
            ])->values()->all(),
            'verdict_counts' => [
                'judged' => $items->whereNotNull('verdict')->count(),
                'hit' => $items->where('verdict', KiokuLetterItem::VERDICT_HIT)->count(),
                'soft_hit' => $items->where('verdict', KiokuLetterItem::VERDICT_SOFT_HIT)->count(),
                'miss' => $items->where('verdict', KiokuLetterItem::VERDICT_MISS)->count(),
                'sensitive_leak' => $items->where('verdict', KiokuLetterItem::VERDICT_SENSITIVE_LEAK)->count(),
            ],
        ];
    }

    /**
     * One lookup for every related memory title in the letter, owner-scoped.
     *
     * @return array<string, string>
     */
    private function relatedTitles(KiokuLetter $letter): array
    {
        $ids = $letter->items
            ->flatMap(fn (KiokuLetterItem $item) => $item->related_memory_ids ?? [])
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return Memory::query()
            ->withoutUserScope()
            ->where('user_id', $letter->user_id)
            ->whereIn('id', $ids)
            ->pluck('title', 'id')
            ->all();
    }
}
