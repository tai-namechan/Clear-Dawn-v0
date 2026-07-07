<?php

namespace App\Console\Commands;

use App\Enums\VideoStatus;
use App\Models\Video;
use App\Services\VideoStorageClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PrunePendingVideosCommand extends Command
{
    protected $signature = 'videos:prune-pending';

    protected $description = 'Remove pending videos that were not finalized within 24 hours';

    public function handle(VideoStorageClient $storageClient): int
    {
        $cutoff = now()->subDay();

        Video::query()
            ->where('status', VideoStatus::Pending)
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($videos) use ($storageClient): void {
                foreach ($videos as $video) {
                    try {
                        $storageClient->delete($video->storage_key);
                        $video->forceDelete();
                    } catch (\Throwable $exception) {
                        Log::warning('Failed to prune pending video.', [
                            'video_id' => $video->id,
                            'storage_key' => $video->storage_key,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        return self::SUCCESS;
    }
}
