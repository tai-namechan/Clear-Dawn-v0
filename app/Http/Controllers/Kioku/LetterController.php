<?php

namespace App\Http\Controllers\Kioku;

use App\Domain\Kioku\Exceptions\KiokuLetterException;
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
 * Weekly concierge letter (docs/product/kioku-final-remaining-
 * implementation.md §14–15). Ownership mismatches are always 404; opening
 * is idempotent so a reload never double-counts references.
 */
class LetterController extends Controller
{
    public function __construct(
        private KiokuLetterEvaluationService $evaluation,
    ) {}

    public function show(Request $request, KiokuLetter $letter): Response
    {
        $this->authorizeOwner($request, $letter);
        $letter->load('items');

        return Inertia::render('Kioku/Letter', [
            'letter' => (new KiokuLetterResource($letter))->resolve(),
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
                'message' => '評価を完了し、キオクに記録を残しました。',
            ]);
        } catch (KiokuLetterException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return redirect()->route('kioku.letters.show', $letter);
    }

    private function authorizeOwner(Request $request, KiokuLetter $letter): void
    {
        abort_unless((int) $letter->user_id === (int) $request->user()->id, 404);
    }
}
