<?php

namespace App\Http\Controllers\Kioku;

use App\Domain\Kioku\Exceptions\KiokuLetterException;
use App\Domain\Kioku\KiokuLetterMode;
use App\Domain\Kioku\Models\KiokuConciergeSchedule;
use App\Domain\Kioku\Models\KiokuLetter;
use App\Domain\Kioku\Models\KiokuLetterItem;
use App\Domain\Kioku\Services\KiokuLetterEvaluationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kioku\StoreLetterVerdictRequest;
use App\Http\Resources\Kioku\KiokuLetterResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Concierge letter HTTP surface
 * (docs/product/kioku-final-remaining-implementation.md §14–15 +
 * docs/product/kioku-concierge-daily-pilot.md).
 */
class LetterController extends Controller
{
    public function __construct(
        private KiokuLetterEvaluationService $evaluation,
    ) {}

    /**
     * Live letter history (+ isolated test letters when present).
     */
    public function index(Request $request): Response
    {
        $userId = (int) $request->user()->id;

        return Inertia::render('Kioku/Letters', [
            'letters' => $this->letterSummaries($userId, KiokuLetterMode::Live, 60),
            'testLetters' => $this->letterSummaries($userId, KiokuLetterMode::Test, 20),
            'letterSchedule' => $this->letterScheduleSummary($userId),
        ]);
    }

    public function preview(Request $request): Response
    {
        $character = (string) $request->query('character', 'shiori');
        if (! in_array($character, KiokuLetter::CHARACTER_VARIANTS, true)) {
            abort(404);
        }

        $case = (string) $request->query('case', 'one');
        $fixture = $this->previewFixture($character, $case);

        return Inertia::render('Kioku/Letter', [
            'letter' => $fixture,
            'preview' => true,
        ]);
    }

    public function show(Request $request, KiokuLetter $letter): Response
    {
        $this->authorizeOwner($request, $letter);
        $letter->load('items');

        return Inertia::render('Kioku/Letter', [
            'letter' => (new KiokuLetterResource($letter))->resolve(),
            'preview' => false,
        ]);
    }

    public function open(Request $request, KiokuLetter $letter): RedirectResponse
    {
        $this->authorizeOwner($request, $letter);

        $this->evaluation->open($letter);

        return redirect()->route('kioku.letters.show', $letter);
    }

    public function storeVerdict(
        StoreLetterVerdictRequest $request,
        KiokuLetter $letter,
        KiokuLetterItem $letterItem,
    ): RedirectResponse {
        $this->authorizeOwner($request, $letter);
        abort_unless($letterItem->letter_id === $letter->id, 404);

        try {
            $this->evaluation->storeVerdict(
                $letter,
                $letterItem,
                (string) $request->validated('verdict'),
                $request->validated('note'),
            );
        } catch (KiokuLetterException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('kioku.letters.show', $letter);
    }

    public function complete(Request $request, KiokuLetter $letter): RedirectResponse
    {
        $this->authorizeOwner($request, $letter);

        try {
            $this->evaluation->complete($letter);

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => $letter->isTest()
                    ? 'テスト便りの確認を完了しました。'
                    : '評価を完了し、キオクに記録を残しました。',
            ]);
        } catch (KiokuLetterException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('kioku.letters.show', $letter);
    }

    public function destroy(Request $request, KiokuLetter $letter): RedirectResponse
    {
        $this->authorizeOwner($request, $letter);
        abort_unless($letter->modeEnum() === KiokuLetterMode::Test, 404);

        $letter->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'テスト便りを削除しました。',
        ]);

        return redirect()->route('kioku.letters.index');
    }

    private function authorizeOwner(Request $request, KiokuLetter $letter): void
    {
        abort_unless((int) $letter->user_id === (int) $request->user()->id, 404);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function letterSummaries(int $userId, KiokuLetterMode $mode, int $limit): array
    {
        return KiokuLetter::query()
            ->where('user_id', $userId)
            ->where('mode', $mode->value)
            ->where('status', '!=', KiokuLetter::STATUS_GENERATING)
            ->withCount([
                'items as judged_count' => fn ($query) => $query->whereNotNull('verdict'),
                'items as hit_count' => fn ($query) => $query->where('verdict', 'hit'),
            ])
            ->orderByDesc('delivery_date')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->map(fn (KiokuLetter $letter): array => [
                'id' => $letter->id,
                'week_start' => $letter->week_start->toDateString(),
                'delivery_date' => $letter->delivery_date->toDateString(),
                'mode' => $letter->mode,
                'cadence' => $letter->cadence,
                'status' => $letter->status,
                'character_variant' => $letter->character_variant,
                'intro' => $letter->intro,
                'item_count' => $letter->item_count,
                'judged_count' => (int) $letter->getAttribute('judged_count'),
                'hit_count' => (int) $letter->getAttribute('hit_count'),
                'opened' => $letter->opened_at !== null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{state: string, pause_reason: string|null, consecutive_unopened: int}|null
     */
    private function letterScheduleSummary(int $userId): ?array
    {
        $schedule = KiokuConciergeSchedule::query()
            ->where('user_id', $userId)
            ->first(['state', 'pause_reason', 'consecutive_unopened']);

        if ($schedule === null) {
            return null;
        }

        return [
            'state' => $schedule->state,
            'pause_reason' => $schedule->pause_reason,
            'consecutive_unopened' => (int) $schedule->consecutive_unopened,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function previewFixture(string $character, string $case): array
    {
        $itemCount = match ($case) {
            'empty' => 0,
            'one' => 1,
            'two' => 2,
            'five' => 5,
            'image_fail' => 1,
            default => abort(404),
        };

        $items = [];
        for ($i = 1; $i <= $itemCount; $i++) {
            $items[] = [
                'id' => "preview-item-{$i}",
                'position' => $i,
                'memory_id' => "preview-memory-{$i}",
                'title' => "プレビュー記憶 {$i}",
                'summary' => 'fixture summary',
                'headline' => "プレビュー見出し {$i}",
                'why_now' => '表示確認用の固定文です。AIは呼ばれていません。',
                'related' => [],
                'verdict' => null,
                'verdict_note' => null,
                'verdict_at' => null,
            ];
        }

        $today = now()->toDateString();

        return [
            'id' => 'preview',
            'week_start' => now()->startOfWeek()->toDateString(),
            'week_end' => now()->startOfWeek()->addDays(6)->toDateString(),
            'mode' => 'live',
            'cadence' => $case === 'five' ? 'weekly' : 'daily',
            'delivery_date' => $today,
            'status' => $itemCount === 0 ? 'empty' : 'published',
            'character_variant' => $character,
            'intro' => $itemCount === 0 ? null : 'これはDBを作らない表示プレビューです。',
            'item_count' => $itemCount,
            'published_at' => now()->toIso8601String(),
            'opened_at' => now()->toIso8601String(),
            'completed_at' => null,
            'evaluation_memory_id' => null,
            'items' => $items,
            'verdict_counts' => [
                'judged' => 0,
                'hit' => 0,
                'soft_hit' => 0,
                'miss' => 0,
                'sensitive_leak' => 0,
            ],
            'force_image_fail' => $case === 'image_fail',
        ];
    }
}
