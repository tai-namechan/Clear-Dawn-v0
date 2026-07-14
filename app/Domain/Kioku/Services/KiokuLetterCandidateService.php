<?php

namespace App\Domain\Kioku\Services;

use App\Domain\Kioku\Models\Memory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Candidate selection for the weekly concierge letter
 * (docs/product/kioku-final-remaining-implementation.md §12).
 *
 * Sensitive memories, non-ready memories, previous letter evaluation logs
 * and recently surfaced memories are excluded here, at the DB query — the
 * AI never receives them. Only title/summary-level fields are sent; never
 * raw_content, transcripts, or audio.
 */
final class KiokuLetterCandidateService
{
    public const MAX_CANDIDATES = 80;

    public const COOLDOWN_DAYS = 14;

    /**
     * @return Collection<int, Memory>
     */
    public function candidatesFor(int $userId): Collection
    {
        return $this->eligibleQuery($userId)
            ->orderByDesc('importance')
            ->orderByRaw('COALESCE(last_referenced_at, captured_at) asc')
            ->orderByDesc('captured_at')
            ->orderBy('id')
            ->limit(self::MAX_CANDIDATES)
            ->get();
    }

    /**
     * Compact JSON-ready payload for the AI. decision memories also carry
     * their review_condition so "why now" can reference it.
     *
     * @param  Collection<int, Memory>  $memories
     * @return array<int, array<string, mixed>>
     */
    public function candidatePayload(Collection $memories): array
    {
        return $memories->map(function (Memory $memory): array {
            $payload = [
                'id' => $memory->id,
                'title' => $memory->title,
                'summary' => $memory->summary,
                'memory_type' => $memory->memory_type,
                'tags' => $memory->tags ?? [],
                'importance' => $memory->importance,
                'captured_at' => $memory->captured_at->toDateString(),
                'last_referenced_at' => $memory->last_referenced_at?->toDateString(),
            ];

            if ($memory->memory_type === 'decision') {
                $reviewCondition = $memory->structured_data['review_condition'] ?? null;
                if (is_string($reviewCondition) && $reviewCondition !== '') {
                    $payload['review_condition'] = $reviewCondition;
                }
            }

            return $payload;
        })->values()->all();
    }

    /**
     * Exclusion breakdown for the --dry-run command output. Counts are
     * successive filters, so each memory appears under exactly one reason.
     *
     * @return array{total: int, not_ready: int, sensitive: int, letter_logs: int, missing_summary: int, cooling_down: int, eligible: int, capped: int}
     */
    public function exclusionBreakdown(int $userId): array
    {
        $base = fn (): Builder => Memory::query()
            ->withoutUserScope()
            ->where('user_id', $userId);

        $total = $base()->count();
        $notReady = $base()->where('status', '!=', 'ready')->count();
        $sensitive = $base()->where('status', 'ready')->where('sensitive', true)->count();
        $letterLogs = $base()
            ->where('status', 'ready')
            ->where('sensitive', false)
            ->where('source_type', 'kioku_letter')
            ->count();
        $missingSummary = $base()
            ->where('status', 'ready')
            ->where('sensitive', false)
            ->where('source_type', '!=', 'kioku_letter')
            ->where(function (Builder $query): void {
                $query->whereNull('summary')->orWhere('summary', '');
            })
            ->count();
        $eligible = $this->eligibleQuery($userId)->count();
        $coolingDown = $total - $notReady - $sensitive - $letterLogs - $missingSummary - $eligible;

        return [
            'total' => $total,
            'not_ready' => $notReady,
            'sensitive' => $sensitive,
            'letter_logs' => $letterLogs,
            'missing_summary' => $missingSummary,
            'cooling_down' => $coolingDown,
            'eligible' => $eligible,
            'capped' => min($eligible, self::MAX_CANDIDATES),
        ];
    }

    /**
     * @return Builder<Memory>
     */
    private function eligibleQuery(int $userId): Builder
    {
        $cutoff = now()->subDays(self::COOLDOWN_DAYS)->toDateTimeString();

        return Memory::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->where('status', 'ready')
            ->where('sensitive', false)
            ->where('source_type', '!=', 'kioku_letter')
            ->whereNotNull('summary')
            ->where('summary', '!=', '')
            ->whereRaw('COALESCE(last_referenced_at, captured_at) <= ?', [$cutoff]);
    }
}
