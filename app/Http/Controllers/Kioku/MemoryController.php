<?php

namespace App\Http\Controllers\Kioku;

use App\Domain\Kioku\Jobs\EnrichMemoryJob;
use App\Domain\Kioku\Jobs\TranscribeMemoryAudioJob;
use App\Domain\Kioku\KiokuLetterMode;
use App\Domain\Kioku\Models\KiokuConciergeSchedule;
use App\Domain\Kioku\Models\KiokuLetter;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Services\CaptureMemoryService;
use App\Domain\Kioku\Services\KiokuSearchService;
use App\Domain\Kioku\Services\KiokuTagNormalizer;
use App\Domain\Kioku\Services\RelatedMemoryService;
use App\Domain\Kioku\Types\MemoryTypeRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kioku\MemoryStatusRequest;
use App\Http\Requests\Kioku\StoreMemoryRequest;
use App\Http\Requests\Kioku\UpdateMemoryTagsRequest;
use App\Http\Resources\Kioku\MemoryResource;
use App\Http\Resources\Kioku\MemoryStatusResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemoryController extends Controller
{
    /**
     * Kioku home: capture + today's letter + recent memories.
     * Search filters redirect to the library for backward compatibility.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        if ($this->hasLibraryFilters($request)) {
            return redirect()->route('kioku.memories.index', $request->query());
        }

        $user = $request->user();
        $userId = (int) $user->id;

        $recent = Memory::query()
            ->where('user_id', $userId)
            ->whereNotIn('status', ['archived'])
            ->orderByDesc('captured_at')
            ->limit(6)
            ->get();

        return Inertia::render('Kioku/Index', [
            'memories' => MemoryResource::collection($recent)->resolve(),
            'transcriptionEnabled' => config('kioku.transcription.provider', 'none') !== 'none',
            'letters' => $this->letterSummaries($userId, KiokuLetterMode::Live, 4),
            'letterSchedule' => $this->letterScheduleSummary($userId),
        ]);
    }

    /**
     * Search / browse past memories (filters, tags, types, views).
     */
    public function library(Request $request, KiokuSearchService $search, MemoryTypeRegistry $registry): Response
    {
        $user = $request->user();
        $query = is_string($request->input('q'))
            ? (trim((string) $request->input('q')) ?: null)
            : null;
        $types = array_values(array_filter(
            (array) $request->input('types', []),
            fn ($type) => is_string($type) && $type !== '',
        ));
        $rawTags = $request->input('tags', []);
        if (is_string($rawTags)) {
            $rawTags = [$rawTags];
        }
        $tags = array_values(array_filter(
            is_array($rawTags) ? $rawTags : [],
            fn ($tag) => is_string($tag) && $tag !== '',
        ));
        $tagModeRaw = $request->input('tag_mode');
        $tagMode = is_string($tagModeRaw) && $tagModeRaw === 'or' ? 'or' : 'and';

        $memories = $search->search(
            userId: (int) $user->id,
            query: $query,
            filters: [
                'types' => $types,
                'tags' => $tags,
                'tag_mode' => $tagMode,
            ],
            limit: 100,
        );

        $owned = Memory::query()
            ->where('user_id', $user->id)
            ->get(['memory_type', 'source_type', 'tags']);

        $typeCounts = $owned
            ->filter(fn (Memory $m) => filled($m->memory_type))
            ->countBy('memory_type')
            ->all();

        $sourceCounts = $owned->countBy('source_type')->all();
        $tagCounts = $this->tagCountsFromOwned($owned);

        return Inertia::render('Kioku/Library', [
            'memories' => MemoryResource::collection($memories)->resolve(),
            'filters' => [
                'q' => $query,
                'types' => $types,
                'tags' => $tags,
                'tag_mode' => $tagMode,
            ],
            'memoryTypes' => collect($registry->all())
                ->map(fn ($type) => ['key' => $type->key(), 'label' => $type->label()])
                ->values()
                ->all(),
            'typeCounts' => $typeCounts,
            'sourceCounts' => $sourceCounts,
            'tagCounts' => $tagCounts,
            'totalCount' => $owned->count(),
            'transcriptionEnabled' => config('kioku.transcription.provider', 'none') !== 'none',
        ]);
    }

    /**
     * Concierge letters for Home / Letters list. Live and test are never mixed
     * (docs/product/kioku-concierge-daily-pilot.md).
     *
     * @return array<int, array<string, mixed>>
     */
    private function letterSummaries(int $userId, KiokuLetterMode $mode, int $limit = 4): array
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
            ->map(fn (KiokuLetter $letter): array => $this->mapLetterSummary($letter))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLetterSummary(KiokuLetter $letter): array
    {
        return [
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
        ];
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
     * @param  Collection<int, Memory>  $owned
     * @return array<int, array{tag: string, count: int}>
     */
    private function tagCountsFromOwned($owned): array
    {
        $counts = [];

        foreach ($owned as $memory) {
            $seen = [];
            foreach ($memory->tags ?? [] as $tag) {
                if ($tag === '' || isset($seen[$tag])) {
                    continue;
                }
                $seen[$tag] = true;
                $counts[$tag] = ($counts[$tag] ?? 0) + 1;
            }
        }

        arsort($counts);

        $result = [];
        foreach ($counts as $tag => $count) {
            $result[] = ['tag' => (string) $tag, 'count' => (int) $count];
            if (count($result) >= 40) {
                break;
            }
        }

        return $result;
    }

    private function hasLibraryFilters(Request $request): bool
    {
        $q = $request->input('q');
        if (is_string($q) && trim($q) !== '') {
            return true;
        }

        $types = $request->input('types');
        if (is_array($types) && $types !== []) {
            return true;
        }
        if (is_string($types) && $types !== '') {
            return true;
        }

        $tags = $request->input('tags');
        if (is_array($tags) && $tags !== []) {
            return true;
        }
        if (is_string($tags) && $tags !== '') {
            return true;
        }

        $tagMode = $request->input('tag_mode');

        return is_string($tagMode) && $tagMode !== '';
    }

    public function status(MemoryStatusRequest $request): JsonResponse
    {
        /** @var list<string> $ids */
        $ids = array_values($request->validated('ids'));

        $found = Memory::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $ids)
            ->get(['id', 'status']);

        return response()->json(
            (new MemoryStatusResource($found, $ids))->resolve(),
        );
    }

    public function store(StoreMemoryRequest $request, CaptureMemoryService $service): RedirectResponse
    {
        $service->captureText(
            user: $request->user(),
            rawContent: (string) $request->validated('raw_content'),
            sourceType: $request->validated('source_type') ?? 'manual',
            clientCaptureId: $request->validated('client_capture_id'),
            capturedAt: $request->validated('captured_at'),
            sensitive: (bool) ($request->validated('sensitive') ?? false),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => '記憶を保存しました。AIが整理中です。',
        ]);

        return redirect()->route('kioku.home');
    }

    public function reenrich(Request $request, Memory $memory): RedirectResponse
    {
        abort_unless((int) $memory->user_id === (int) $request->user()->id, 404);

        // Conditional update so an in-flight enrichment (captured/enriching)
        // cannot be reset mid-run. Clearing memory_type is required: the job
        // treats memory_type !== null as "classify done" and would skip it.
        $reset = Memory::query()
            ->whereKey($memory->id)
            ->whereIn('status', ['ready', 'failed'])
            ->update([
                'memory_type' => null,
                'summary' => null,
                'structured_data' => null,
                'tags' => null,
                'status' => 'captured',
            ]);

        if ($reset === 1) {
            EnrichMemoryJob::dispatch($memory->id);
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'AIがもう一度整理しています。',
            ]);
        } else {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => 'この記憶は現在整理中です。',
            ]);
        }

        return redirect()->route('kioku.memories.show', $memory);
    }

    /**
     * Owner-only edit of the derived tag list (interpretation layer,
     * docs/architecture/kioku-knowledge-retrieval.md §2). Facts stay
     * untouched — raw_content, transcript_text and audio assets are never
     * written here — and no AI re-enrichment is triggered. The cached
     * related links are recomputed because their score uses tags.
     */
    public function updateTags(
        UpdateMemoryTagsRequest $request,
        Memory $memory,
        KiokuTagNormalizer $normalizer,
        RelatedMemoryService $relatedMemoryService,
    ): RedirectResponse {
        abort_unless((int) $memory->user_id === (int) $request->user()->id, 404);

        $tags = $normalizer->normalize($request->validated('tags') ?? []);

        $memory->update(['tags' => $tags === [] ? null : $tags]);
        $relatedMemoryService->cacheRelated($memory);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'タグを更新しました。',
        ]);

        return redirect()->route('kioku.memories.show', $memory);
    }

    public function retryTranscription(Request $request, Memory $memory): RedirectResponse
    {
        abort_unless((int) $memory->user_id === (int) $request->user()->id, 404);

        if (config('kioku.transcription.provider', 'none') === 'none') {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => '文字起こしは未設定です。原音声は保存されています。',
            ]);

            return redirect()->route('kioku.memories.show', $memory);
        }

        // Conditional update so an in-flight transcription cannot be reset.
        $reset = Memory::query()
            ->whereKey($memory->id)
            ->where('source_type', 'voice')
            ->where('transcription_status', 'failed')
            ->update([
                'transcription_status' => 'pending',
                'status' => 'captured',
            ]);

        if ($reset === 1) {
            TranscribeMemoryAudioJob::dispatch($memory->id);
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => '文字起こしをもう一度実行しています。',
            ]);
        } else {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => 'この記憶は現在処理中か、再実行の対象ではありません。',
            ]);
        }

        return redirect()->route('kioku.memories.show', $memory);
    }

    /**
     * Streams the original audio from the private disk with owner
     * authorization. Audio never gets a public URL.
     *
     * Missing files must 404 (not 500): Storage::response() calls size()
     * before streaming and throws UnableToRetrieveMetadata when the object
     * is gone — common after ephemeral local disk loss on Laravel Cloud.
     */
    public function audio(Request $request, Memory $memory): StreamedResponse
    {
        abort_unless((int) $memory->user_id === (int) $request->user()->id, 404);

        $asset = $memory->audioAsset();
        abort_if($asset === null, 404);

        $disk = Storage::disk($asset->disk);
        abort_unless($disk->exists($asset->path), 404);

        return $disk->response($asset->path, null, [
            'Content-Type' => $asset->mime_type,
            'Cache-Control' => 'private, max-age=0, no-store',
        ]);
    }

    public function show(
        Request $request,
        Memory $memory,
        RelatedMemoryService $relatedMemoryService,
    ): Response {
        abort_unless((int) $memory->user_id === (int) $request->user()->id, 404);

        $related = $relatedMemoryService->forMemory($memory);

        return Inertia::render('Kioku/Detail', [
            'memory' => (new MemoryResource($memory))->resolve(),
            'related' => MemoryResource::collection($related)->resolve(),
            'transcriptionEnabled' => config('kioku.transcription.provider', 'none') !== 'none',
        ]);
    }
}
