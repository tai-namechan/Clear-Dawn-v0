<?php

namespace App\Services;

use App\Enums\VideoStatus;
use App\Models\Video;

class DeleteVideoService
{
    public function __construct(
        private readonly VideoStorageClient $storageClient,
    ) {}

    public function handle(Video $video): void
    {
        $this->storageClient->delete($video->storage_key);

        if ($video->status === VideoStatus::Pending) {
            $video->forceDelete();

            return;
        }

        $video->delete();
    }
}
