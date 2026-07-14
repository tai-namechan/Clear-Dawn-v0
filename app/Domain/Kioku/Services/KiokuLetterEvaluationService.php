<?php

namespace App\Domain\Kioku\Services;

use App\Domain\Kioku\Exceptions\KiokuLetterException;
use App\Domain\Kioku\Models\KiokuLetter;
use App\Domain\Kioku\Models\KiokuLetterItem;
use App\Domain\Kioku\Models\Memory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Letter evaluation lifecycle: open → per-item verdicts → complete
 * (docs/product/kioku-final-remaining-implementation.md §16).
 *
 * Opening is idempotent (a reload never double-counts references), a
 * sensitive_leak verdict halts the letter, and completing writes exactly one
 * evaluation memory — protected by a transaction plus evaluation_memory_id.
 * Verdicts are frozen after completion.
 */
final class KiokuLetterEvaluationService
{
    public function __construct(
        private MemoryReferenceService $references,
    ) {}

    /**
     * First open moves published → opened and marks the shown memories as
     * referenced exactly once. Empty letters just record the open time.
     */
    public function open(KiokuLetter $letter): void
    {
        $claimed = KiokuLetter::query()
            ->withoutUserScope()
            ->whereKey($letter->id)
            ->where('status', KiokuLetter::STATUS_PUBLISHED)
            ->whereNull('opened_at')
            ->update([
                'status' => KiokuLetter::STATUS_OPENED,
                'opened_at' => now(),
            ]);

        if ($claimed === 1) {
            $memoryIds = $letter->items()
                ->get()
                ->map(fn (KiokuLetterItem $item): string => $item->memory_id)
                ->values()
                ->all();
            $this->references->markReferenced($memoryIds);

            return;
        }

        KiokuLetter::query()
            ->withoutUserScope()
            ->whereKey($letter->id)
            ->where('status', KiokuLetter::STATUS_EMPTY)
            ->whereNull('opened_at')
            ->update(['opened_at' => now()]);
    }

    /**
     * Verdicts may be revised until the letter is completed. sensitive_leak
     * halts the letter immediately (§17: generation stops on the first leak).
     */
    public function storeVerdict(KiokuLetter $letter, KiokuLetterItem $item, string $verdict, ?string $note): void
    {
        if (! in_array($verdict, KiokuLetterItem::VERDICTS, true)) {
            throw new KiokuLetterException("Unknown verdict [{$verdict}].");
        }

        if ($letter->isCompleted()) {
            throw new KiokuLetterException('この手紙の評価は完了済みです。判定は変更できません。');
        }

        DB::transaction(function () use ($letter, $item, $verdict, $note): void {
            $item->update([
                'verdict' => $verdict,
                'verdict_note' => $note !== null && trim($note) !== '' ? trim($note) : null,
                'verdict_at' => now(),
            ]);

            if ($verdict === KiokuLetterItem::VERDICT_SENSITIVE_LEAK) {
                $letter->update(['status' => KiokuLetter::STATUS_HALTED]);
            } elseif (in_array($letter->status, [KiokuLetter::STATUS_PUBLISHED, KiokuLetter::STATUS_OPENED], true)) {
                $letter->update(['status' => KiokuLetter::STATUS_EVALUATING]);
            }
        });
    }

    /**
     * Completing requires every item to be judged and stores exactly one
     * evaluation memory directly as ready (never enriched). A halted letter
     * keeps its halted status as the experiment's stop marker.
     */
    public function complete(KiokuLetter $letter): Memory
    {
        return DB::transaction(function () use ($letter): Memory {
            /** @var KiokuLetter $locked */
            $locked = KiokuLetter::query()
                ->withoutUserScope()
                ->whereKey($letter->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->completed_at !== null) {
                $existing = $locked->evaluationMemory()->withoutUserScope()->first();
                if ($existing !== null) {
                    return $existing;
                }

                throw new KiokuLetterException('この手紙の評価は完了済みです。');
            }

            $items = $locked->items()->get();
            if ($items->isEmpty()) {
                throw new KiokuLetterException('この手紙には評価する項目がありません。');
            }

            if ($items->contains(fn (KiokuLetterItem $item): bool => $item->verdict === null)) {
                throw new KiokuLetterException('未判定の項目があります。すべて判定してから完了してください。');
            }

            $memory = $this->createEvaluationMemory($locked, $items);

            $locked->update([
                'status' => $locked->status === KiokuLetter::STATUS_HALTED
                    ? KiokuLetter::STATUS_HALTED
                    : KiokuLetter::STATUS_EVALUATED,
                'completed_at' => now(),
                'evaluation_memory_id' => $memory->id,
            ]);

            return $memory;
        });
    }

    /**
     * @param  Collection<int, KiokuLetterItem>  $items
     */
    private function createEvaluationMemory(KiokuLetter $letter, Collection $items): Memory
    {
        $itemCount = $items->count();
        $hits = $items->where('verdict', KiokuLetterItem::VERDICT_HIT)->count();
        $softHits = $items->where('verdict', KiokuLetterItem::VERDICT_SOFT_HIT)->count();

        $weekNumber = KiokuLetter::query()
            ->withoutUserScope()
            ->where('user_id', $letter->user_id)
            ->where('week_start', '<=', $letter->week_start)
            ->count();
        $weekEnd = $letter->week_start->addDays(6)->toDateString();

        $openedWithin24h = $letter->opened_at !== null
            && $letter->published_at !== null
            && $letter->opened_at->lessThanOrEqualTo($letter->published_at->addDay());

        return Memory::query()->create([
            'user_id' => $letter->user_id,
            'source_type' => 'kioku_letter',
            'memory_type' => 'event',
            'title' => "コンシェルジュ手紙 第{$weekNumber}週（{$weekEnd}）",
            'raw_content' => $this->letterFullText($letter, $items),
            'summary' => "コンシェルジュ手紙の評価: HIT {$hits} / {$itemCount}件",
            'structured_data' => [
                'experiment' => 'kioku_concierge_v1',
                'week_start' => $letter->week_start->toDateString(),
                'character_variant' => $letter->character_variant,
                'opened_within_24h' => $openedWithin24h,
                'items' => $items->map(fn (KiokuLetterItem $item): array => [
                    'memory_id' => $item->memory_id,
                    'verdict' => $item->verdict,
                    'note' => $item->verdict_note,
                ])->values()->all(),
                'hit_rate' => round($hits / $itemCount, 2),
                'useful_rate' => round(($hits + $softHits) / $itemCount, 2),
            ],
            'tags' => ['コンシェルジュ実験', '自動発火', '評価データ'],
            'captured_at' => now(),
            'importance' => 3,
            'sensitive' => false,
            'status' => 'ready',
        ]);
    }

    /**
     * The generated letter as plain text (intro + items). Verdicts live in
     * structured_data, not in the letter body itself.
     *
     * @param  Collection<int, KiokuLetterItem>  $items
     */
    private function letterFullText(KiokuLetter $letter, Collection $items): string
    {
        $blocks = ["今週のキオク便り（{$letter->week_start->toDateString()} の週）"];

        if ($letter->intro !== null && $letter->intro !== '') {
            $blocks[] = $letter->intro;
        }

        foreach ($items as $item) {
            $blocks[] = "{$item->position}. {$item->headline}\nなぜ今: {$item->why_now}\n元の記憶: {$item->title_snapshot}";
        }

        return implode("\n\n", $blocks);
    }
}
