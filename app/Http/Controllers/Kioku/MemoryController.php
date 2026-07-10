<?php

namespace App\Http\Controllers\Kioku;

use App\Domain\Kioku\Jobs\EnrichMemoryJob;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Services\KiokuSearchService;
use App\Domain\Kioku\Services\RelatedMemoryService;
use App\Domain\Kioku\Types\MemoryTypeRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kioku\StoreMemoryRequest;
use App\Http\Resources\Kioku\MemoryResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
        ]);
    }

    public function store(StoreMemoryRequest $request): RedirectResponse
    {
        $user = $request->user();
        $content = trim((string) $request->validated('raw_content'));
        $sourceType = $request->validated('source_type') ?? 'manual';
        if ($sourceType === 'manual' && filter_var($content, FILTER_VALIDATE_URL)) {
            $sourceType = 'url';
        }

        $memory = Memory::query()->create([
            'user_id' => $user->id,
            'source_type' => $sourceType,
            'memory_type' => null,
            'title' => '整理中…',
            'raw_content' => $content,
            'captured_at' => $request->validated('captured_at') ?? now(),
            'sensitive' => (bool) ($request->validated('sensitive') ?? false),
            'status' => 'captured',
        ]);

        EnrichMemoryJob::dispatch($memory->id)->afterResponse();

        $memory->update(['status' => 'enriching']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => '記憶を保存しました。AIが整理中です。',
        ]);

        return redirect()->route('kioku.home');
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
        ]);
    }
}
