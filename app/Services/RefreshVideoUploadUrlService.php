<?php

namespace App\Services;

use App\Models\Video;

class RefreshVideoUploadUrlService
{
    public function __construct(
        private readonly VideoStorageClient $storageClient,
    ) {}

    /**
     * @return array{mode: string, video_id: string, uploads: list<array{url: string, headers: array<string, string>, expires_at: string}>}
     */
    public function handle(Video $video): array
    {
        $upload = $this->storageClient->temporaryUploadUrl(
            $video->storage_key,
            CreateVideoUploadUrlService::UploadUrlExpiryMinutes,
            $video->mime_type,
        );

        return [
            'mode' => 'single',
            'video_id' => $video->id,
            'uploads' => [$upload],
        ];
    }
}
