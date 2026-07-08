<?php

namespace App\Services;

use App\Enums\VideoStatus;
use App\Models\User;
use App\Models\Video;
use App\Support\VideoMimeType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateVideoUploadUrlService
{
    public const int PendingLimit = 5;

    public const int UploadUrlExpiryMinutes = 15;

    public function __construct(
        private readonly VideoStorageClient $storageClient,
    ) {}

    /**
     * @return array{mode: string, video_id: string, uploads: list<array{url: string, headers: array<string, string>, expires_at: string}>}
     */
    public function handle(
        User $user,
        string $title,
        string $mimeType,
        int $sizeBytes,
        ?int $durationSeconds,
    ): array {
        $pendingCount = Video::query()
            ->where('user_id', $user->id)
            ->where('status', VideoStatus::Pending)
            ->count();

        if ($pendingCount >= self::PendingLimit) {
            throw ValidationException::withMessages([
                'upload' => ['アップロード中の動画が多すぎます。'],
            ]);
        }

        return DB::transaction(function () use ($user, $title, $mimeType, $sizeBytes, $durationSeconds): array {
            $videoId = (string) Str::ulid();
            $extension = VideoMimeType::extensionFor($mimeType);
            $storageKey = "videos/{$user->id}/{$videoId}.{$extension}";

            $video = Video::query()->create([
                'id' => $videoId,
                'user_id' => $user->id,
                'title' => $title,
                'status' => VideoStatus::Pending,
                'storage_key' => $storageKey,
                'mime_type' => $mimeType,
                'size_bytes' => $sizeBytes,
                'duration_seconds' => $durationSeconds,
            ]);

            $upload = $this->storageClient->temporaryUploadUrl(
                $storageKey,
                self::UploadUrlExpiryMinutes,
                $mimeType,
            );

            return [
                'mode' => 'single',
                'video_id' => $video->id,
                'uploads' => [$upload],
            ];
        });
    }
}
