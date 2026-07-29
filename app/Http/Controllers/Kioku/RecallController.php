<?php

namespace App\Http\Controllers\Kioku;

use App\Domain\Kioku\Models\KiokuRecallFeedback;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Services\HybridSearchService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kioku\StoreRecallFeedbackRequest;
use App\Http\Resources\Kioku\MemoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecallController extends Controller
{
    public function search(Request $request, HybridSearchService $search): JsonResponse
    {
        if (! config('kioku.semantic_search.enabled', false) && ! $request->boolean('allow_fallback', true)) {
            return response()->json(['message' => 'Semantic search is disabled.'], 404);
        }

        $query = trim((string) $request->input('q', ''));
        $types = array_values(array_filter(
            (array) $request->input('types', []),
            fn ($type) => is_string($type) && $type !== '',
        ));
        $tags = array_values(array_filter(
            (array) $request->input('tags', []),
            fn ($tag) => is_string($tag) && $tag !== '',
        ));

        $result = $search->recall(
            userId: (int) $request->user()->id,
            query: $query,
            filters: [
                'types' => $types,
                'tags' => $tags,
                'tag_mode' => $request->input('tag_mode') === 'or' ? 'or' : 'and',
                'semantic' => $request->boolean('semantic', true),
            ],
            limit: (int) $request->input('limit', 4),
        );

        return response()->json([
            'mode' => $result['mode'],
            'session_id' => $result['session_id'],
            'query_hash' => $result['query_hash'],
            'results' => collect($result['results'])->map(fn (array $row): array => [
                'rank' => $row['rank'],
                'score' => $row['score'],
                'reason' => $row['reason'],
                'tag_rank' => $row['tag_rank'],
                'fulltext_rank' => $row['fulltext_rank'],
                'vector_rank' => $row['vector_rank'],
                'memory' => (new MemoryResource($row['memory']))->resolve(),
            ])->values()->all(),
        ]);
    }

    public function feedback(StoreRecallFeedbackRequest $request): JsonResponse
    {
        if (! config('kioku.recall_feedback.enabled', false)) {
            return response()->json(['message' => 'Recall feedback is disabled.'], 404);
        }

        $userId = (int) $request->user()->id;
        $memoryId = $request->validated('memory_id');

        if ($memoryId !== null) {
            $owned = Memory::query()
                ->withoutUserScope()
                ->where('user_id', $userId)
                ->whereKey($memoryId)
                ->exists();
            if (! $owned) {
                abort(404);
            }
        }

        $feedback = KiokuRecallFeedback::query()->create([
            'user_id' => $userId,
            ...$request->safe()->only([
                'search_session_id',
                'query_hash',
                'memory_id',
                'shown_rank',
                'tag_rank',
                'fulltext_rank',
                'vector_rank',
                'final_score',
                'verdict',
            ]),
        ]);

        return response()->json(['id' => $feedback->id], 201);
    }
}
