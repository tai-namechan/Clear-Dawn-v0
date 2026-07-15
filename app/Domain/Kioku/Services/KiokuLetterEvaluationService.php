<?php

namespace App\Domain\Kioku\Services;

use App\Domain\Kioku\Exceptions\KiokuLetterException;
use App\Domain\Kioku\KiokuConciergeScheduleState;
use App\Domain\Kioku\KiokuLetterCadence;
use App\Domain\Kioku\KiokuLetterMode;
use App\Domain\Kioku\Models\KiokuConciergeSchedule;
use App\Domain\Kioku\Models\KiokuLetter;
use App\Domain\Kioku\Models\KiokuLetterItem;
use App\Domain\Kioku\Models\Memory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Letter evaluation lifecycle: open → per-item verdicts → complete
 * (docs/product/kioku-final-remaining-implementation.md §16 +
 * docs/product/kioku-concierge-daily-pilot.md).
 */
final class KiokuLetterEvaluationService
{
    public function __construct(
        private MemoryReferenceService $references,
    ) {}

    /**
     * First open is claimed by opened_at IS NULL (not only status=published)
     * so a verdict that races ahead of open cannot erase the open record.
     * Status is never forced back to published. Memory references update once
     * for live letters only.
     */
    public function open(KiokuLetter $letter): void
    {
        DB::transaction(function () use ($letter): void {
            /** @var KiokuLetter|null $locked */
            $locked = KiokuLetter::query()
                ->withoutUserScope()
                ->whereKey($letter->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null || $locked->opened_at !== null) {
                return;
            }

            if (! in_array($locked->status, [
                KiokuLetter::STATUS_PUBLISHED,
                KiokuLetter::STATUS_EMPTY,
                KiokuLetter::STATUS_EVALUATING,
                KiokuLetter::STATUS_HALTED,
            ], true)) {
                return;
            }

            $update = ['opened_at' => now()];
            if ($locked->status === KiokuLetter::STATUS_PUBLISHED) {
                $update['status'] = KiokuLetter::STATUS_OPENED;
            }

            $locked->update($update);

            if (! $locked->isLive()) {
                return;
            }

            $memoryIds = $locked->items()
                ->get()
                ->map(fn (KiokuLetterItem $item): string => $item->memory_id)
                ->values()
                ->all();

            $this->references->markReferenced($memoryIds);
        });
    }

    /**
     * Lock order (all paths): Letter → Item. completed_at is re-checked
     * inside the transaction. sensitive_leak quarantines the source memory
     * and halts the letter + schedule in the same transaction.
     */
    public function storeVerdict(KiokuLetter $letter, KiokuLetterItem $item, string $verdict, ?string $note): void
    {
        if (! in_array($verdict, KiokuLetterItem::VERDICTS, true)) {
            throw new KiokuLetterException("Unknown verdict [{$verdict}].");
        }

        DB::transaction(function () use ($letter, $item, $verdict, $note): void {
            /** @var KiokuLetter $lockedLetter */
            $lockedLetter = KiokuLetter::query()
                ->withoutUserScope()
                ->whereKey($letter->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedLetter->completed_at !== null) {
                throw new KiokuLetterException('この手紙の評価は完了済みです。判定は変更できません。');
            }

            /** @var KiokuLetterItem $lockedItem */
            $lockedItem = KiokuLetterItem::query()
                ->whereKey($item->id)
                ->where('letter_id', $lockedLetter->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedItem->update([
                'verdict' => $verdict,
                'verdict_note' => $note !== null && trim($note) !== '' ? trim($note) : null,
                'verdict_at' => now(),
            ]);

            if ($verdict === KiokuLetterItem::VERDICT_SENSITIVE_LEAK) {
                $this->applySensitiveHalt($lockedLetter, $lockedItem);
            } elseif (in_array($lockedLetter->status, [KiokuLetter::STATUS_PUBLISHED, KiokuLetter::STATUS_OPENED], true)) {
                $lockedLetter->update(['status' => KiokuLetter::STATUS_EVALUATING]);
            }
        });
    }

    /**
     * Completing requires every item to be judged (empty live letters may
     * complete with null rates). Test letters never write evaluation memories
     * except that sensitive_leak quarantine already happened at verdict time.
     */
    public function complete(KiokuLetter $letter): ?Memory
    {
        return DB::transaction(function () use ($letter): ?Memory {
            /** @var KiokuLetter $locked */
            $locked = KiokuLetter::query()
                ->withoutUserScope()
                ->whereKey($letter->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->completed_at !== null) {
                if ($locked->evaluation_memory_id !== null) {
                    return $locked->evaluationMemory()->withoutUserScope()->first();
                }

                return null;
            }

            $items = $locked->items()->lockForUpdate()->get();

            if ($items->isNotEmpty() && $items->contains(fn (KiokuLetterItem $item): bool => $item->verdict === null)) {
                throw new KiokuLetterException('未判定の項目があります。すべて判定してから完了してください。');
            }

            $memory = null;
            if ($locked->isLive()) {
                $memory = $this->createEvaluationMemory($locked, $items);
            }

            $locked->update([
                'status' => $locked->status === KiokuLetter::STATUS_HALTED
                    ? KiokuLetter::STATUS_HALTED
                    : ($items->isEmpty() ? KiokuLetter::STATUS_EMPTY : KiokuLetter::STATUS_EVALUATED),
                'completed_at' => now(),
                'evaluation_memory_id' => $memory?->id,
                'opened_at' => $locked->opened_at ?? now(),
            ]);

            return $memory;
        });
    }

    private function applySensitiveHalt(KiokuLetter $letter, KiokuLetterItem $item): void
    {
        Memory::query()
            ->withoutUserScope()
            ->whereKey($item->memory_id)
            ->where('user_id', $letter->user_id)
            ->update(['sensitive' => true]);

        $letter->update([
            'status' => KiokuLetter::STATUS_HALTED,
            'halted_at' => $letter->halted_at ?? now(),
        ]);

        KiokuConciergeSchedule::query()
            ->withoutUserScope()
            ->where('user_id', $letter->user_id)
            ->whereIn('state', [
                KiokuConciergeScheduleState::Active->value,
                KiokuConciergeScheduleState::Paused->value,
            ])
            ->update([
                'state' => KiokuConciergeScheduleState::Halted->value,
                'pause_reason' => 'sensitive_leak',
                'next_delivery_at' => null,
            ]);
    }

    /**
     * @param  Collection<int, KiokuLetterItem>  $items
     */
    private function createEvaluationMemory(KiokuLetter $letter, Collection $items): Memory
    {
        $itemCount = $items->count();
        $hits = $items->where('verdict', KiokuLetterItem::VERDICT_HIT)->count();
        $softHits = $items->where('verdict', KiokuLetterItem::VERDICT_SOFT_HIT)->count();
        $misses = $items->where('verdict', KiokuLetterItem::VERDICT_MISS)->count();
        $leaks = $items->where('verdict', KiokuLetterItem::VERDICT_SENSITIVE_LEAK)->count();
        $empty = $itemCount === 0;

        $openedWithin24h = $letter->opened_at !== null
            && $letter->published_at !== null
            && $letter->opened_at->lessThanOrEqualTo($letter->published_at->copy()->addDay());

        $isDaily = $letter->cadenceEnum() === KiokuLetterCadence::Daily;
        $experiment = $isDaily ? 'kioku_concierge_daily_pilot_v1' : 'kioku_concierge_v1';

        $title = $isDaily
            ? 'コンシェルジュ手紙 '.$letter->delivery_date->toDateString()
            : $this->weeklyTitle($letter);

        $hasSensitiveContent = $leaks > 0 || $letter->status === KiokuLetter::STATUS_HALTED;

        $structured = [
            'experiment' => $experiment,
            'mode' => $letter->mode,
            'cadence' => $letter->cadence,
            'week_start' => $letter->week_start->toDateString(),
            'delivery_date' => $letter->delivery_date->toDateString(),
            'pilot_day' => $letter->pilot_day,
            'character_variant' => $letter->character_variant,
            'character' => $letter->character_variant,
            'opened_within_24h' => $openedWithin24h,
            'item_count' => $itemCount,
            'empty' => $empty,
            'verdict_counts' => [
                'hit' => $hits,
                'soft_hit' => $softHits,
                'miss' => $misses,
                'sensitive_leak' => $leaks,
            ],
            'items' => $items->map(fn (KiokuLetterItem $item): array => [
                'memory_id' => $item->memory_id,
                'verdict' => $item->verdict,
                'note' => $item->verdict_note,
            ])->values()->all(),
            'hit_rate' => $empty ? null : round($hits / $itemCount, 2),
            'useful_rate' => $empty ? null : round(($hits + $softHits) / $itemCount, 2),
            'consecutive_unopened' => null,
        ];

        return Memory::query()->create([
            'user_id' => $letter->user_id,
            'source_type' => 'kioku_letter',
            'memory_type' => 'event',
            'title' => $title,
            'raw_content' => $this->letterFullText($letter, $items),
            'summary' => $empty
                ? 'コンシェルジュ手紙の評価: 0件（empty）'
                : "コンシェルジュ手紙の評価: HIT {$hits} / {$itemCount}件",
            'structured_data' => $structured,
            'tags' => ['コンシェルジュ実験', '自動発火', '評価データ'],
            'captured_at' => now(),
            'importance' => 3,
            'sensitive' => $hasSensitiveContent,
            'status' => 'ready',
        ]);
    }

    private function weeklyTitle(KiokuLetter $letter): string
    {
        $weekNumber = KiokuLetter::query()
            ->withoutUserScope()
            ->where('user_id', $letter->user_id)
            ->where('mode', KiokuLetterMode::Live->value)
            ->where('cadence', KiokuLetterCadence::Weekly->value)
            ->where('week_start', '<=', $letter->week_start)
            ->count();
        $weekEnd = $letter->week_start->copy()->addDays(6)->toDateString();

        return "コンシェルジュ手紙 第{$weekNumber}週（{$weekEnd}）";
    }

    /**
     * @param  Collection<int, KiokuLetterItem>  $items
     */
    private function letterFullText(KiokuLetter $letter, Collection $items): string
    {
        $label = $letter->cadenceEnum() === KiokuLetterCadence::Daily
            ? "キオク便り（{$letter->delivery_date->toDateString()}）"
            : "今週のキオク便り（{$letter->week_start->toDateString()} の週）";

        $blocks = [$label];

        if ($letter->intro !== null && $letter->intro !== '') {
            $blocks[] = $letter->intro;
        }

        if ($items->isEmpty()) {
            $blocks[] = '（届ける記憶はありませんでした）';
        }

        foreach ($items as $item) {
            $blocks[] = "{$item->position}. {$item->headline}\nなぜ今: {$item->why_now}\n元の記憶: {$item->title_snapshot}";
        }

        return implode("\n\n", $blocks);
    }
}
