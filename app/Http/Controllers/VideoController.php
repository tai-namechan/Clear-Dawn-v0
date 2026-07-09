<?php

namespace App\Http\Controllers;

use App\Enums\VideoStatus;
use App\Http\Requests\Videos\StoreVideoUploadUrlRequest;
use App\Http\Requests\Videos\UpdateVideoRequest;
use App\Http\Resources\VideoResource;
use App\Models\Video;
use App\Queries\GetVideosQuery;
use App\Services\CreateVideoUploadUrlService;
use App\Services\DeleteVideoService;
use App\Services\FinalizeVideoService;
use App\Services\RefreshVideoUploadUrlService;
use App\Services\UpdateVideoService;
use App\Services\VideoStorageClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class VideoController extends Controller
{
    public function index(Request $request, GetVideosQuery $query): Response|JsonResponse
    {
        // JSON list for pickers (avoids Inertia asset-version 409)
        if ($request->wantsJson() && ! $request->headers->has('X-Inertia')) {
            $videos = Video::query()
                ->where('user_id', $request->user()->id)
                ->where('status', VideoStatus::Ready)
                ->orderByDesc('created_at')
                ->limit(100)
                ->get();

            return response()->json([
                'videos' => VideoResource::collection($videos)->resolve(),
            ]);
        }

        $videos = $query->handle($request->user());

        return Inertia::render('Videos/Index', [
            'videos' => VideoResource::collection($videos),
        ]);
    }

    public function createUploadUrl(
        StoreVideoUploadUrlRequest $request,
        CreateVideoUploadUrlService $service,
    ): JsonResponse {
        $validated = $request->validated();

        $result = $service->handle(
            $request->user(),
            $validated['title'],
            $validated['mime_type'],
            (int) $validated['size_bytes'],
            isset($validated['duration_seconds']) ? (int) $validated['duration_seconds'] : null,
        );

        return response()->json($result);
    }

    public function refreshUploadUrl(
        Video $video,
        RefreshVideoUploadUrlService $service,
    ): JsonResponse {
        Gate::authorize('refreshUploadUrl', $video);

        return response()->json($service->handle($video));
    }

    public function finalize(Video $video, FinalizeVideoService $service): JsonResponse
    {
        Gate::authorize('finalize', $video);

        return response()->json($service->handle($video));
    }

    public function streamUrl(Video $video, VideoStorageClient $storageClient): JsonResponse
    {
        Gate::authorize('streamUrl', $video);

        $result = $storageClient->temporaryUrl($video->storage_key, 10);

        return response()->json($result);
    }

    public function update(
        UpdateVideoRequest $request,
        Video $video,
        UpdateVideoService $service,
    ): JsonResponse {
        Gate::authorize('update', $video);

        $updated = $service->handle($video, $request->validated());

        return response()->json([
            'video' => VideoResource::make($updated)->resolve(),
        ]);
    }

    public function destroy(Video $video, DeleteVideoService $service): JsonResponse
    {
        Gate::authorize('delete', $video);

        $service->handle($video);

        return response()->json(['deleted' => true]);
    }
}
