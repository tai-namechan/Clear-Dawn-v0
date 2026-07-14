<?php

namespace App\Http\Controllers\Kioku;

use App\Domain\Kioku\Jobs\EnrichMemoryJob;
use App\Domain\Kioku\Jobs\TranscribeMemoryAudioJob;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Services\CaptureMemoryService;
use App\Domain\Kioku\Services\KiokuSearchService;
use App\Domain\Kioku\Services\RelatedMemoryService;
use App\Domain\Kioku\Types\MemoryTypeRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kioku\MemoryStatusRequest;
use App\Http\Requests\Kioku\StoreMemoryRequest;
use App\Http\Resources\Kioku\MemoryResource;
use App\Http\Resources\Kioku\MemoryStatusResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemoryController extends Controller
{
    public function index(Request $request, KiokuSearchService $search, MemoryTypeRegistry $registry): Response
    {
        $user = $request->user();
        $query = $request->string('q')->toString() ?: null;
        $types = array_values(array_filter((array) $request->input('types', [])));

        $memories = $search->search(
            userId: (int) $user->id,
            query: $query,
            filters: [
                'types' => $types,
            ],
            limit: 100,
        );

        $owned = Memory::query()
            ->where('user_id', $user->id)
            ->get(['memory_type', 'source_type']);

        $typeCounts = $owned
            ->filter(fn (Memory $m) => filled($m->memory_type))
            ->countBy('memory_type')
            ->all();

        $sourceCounts = $owned->countBy('source_type')->all();

        return Inertia::render('Kioku/Index', [
            'memories' => MemoryResource::collection($memories)->resolve(),
            'filters' => [
                'q' => $query,
                'types' => $types,
            ],
            'memoryTypes' => collect($registry->all())
                ->map(fn ($type) => ['key' => $type->key(), 'label' => $type->label()])
                ->values()
                ->all(),
            'typeCounts' => $typeCounts,
            'sourceCounts' => $sourceCounts,
            'totalCount' => $owned->count(),
            'transcriptionEnabled' => config('kioku.transcription.provider', 'none') !== 'none',
        ]);
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
     */
    public function audio(Request $request, Memory $memory): StreamedResponse
    {
        abort_unless((int) $memory->user_id === (int) $request->user()->id, 404);

        $asset = $memory->audioAsset();
        abort_if($asset === null, 404);

        return Storage::disk($asset->disk)->response($asset->path, null, [
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
