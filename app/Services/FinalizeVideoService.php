<?php

namespace App\Services;

use App\Enums\VideoStatus;
use App\Models\Video;
use App\Support\VideoMimeType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinalizeVideoService
{
    public function __construct(
        private readonly VideoStorageClient $storageClient,
    ) {}

    /**
     * @return array{status: string, video_id: string}
     */
    public function handle(Video $video): array
    {
        $lockedVideo = null;
        $readyPayload = null;
        $rejected = false;

        DB::transaction(function () use ($video, &$lockedVideo, &$readyPayload, &$rejected): void {
            /** @var Video $locked */
            $locked = Video::query()
                ->whereKey($video->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedVideo = $locked;

            if ($locked->status === VideoStatus::Ready) {
                $readyPayload = [
                    'status' => VideoStatus::Ready->value,
                    'video_id' => $locked->id,
                ];

                return;
            }

            $inspection = $this->inspectStorage($locked);

            if (! $inspection['valid']) {
                $rejected = true;

                return;
            }

            $locked->update([
                'status' => VideoStatus::Ready,
                'size_bytes' => $inspection['size'],
            ]);

            $readyPayload = [
                'status' => VideoStatus::Ready->value,
                'video_id' => $locked->id,
            ];
        });

        if ($rejected && $lockedVideo !== null) {
            $this->rejectPendingVideo($lockedVideo);

            throw ValidationException::withMessages([
                'finalize' => ['動画のアップロードを確認できませんでした。'],
            ]);
        }

        if ($readyPayload !== null) {
            return $readyPayload;
        }

        throw new \LogicException('Unexpected finalize state.');
    }

    /**
     * @return array{valid: bool, size: int}
     */
    private function inspectStorage(Video $video): array
    {
        if (! $this->storageClient->exists($video->storage_key)) {
            return ['valid' => false, 'size' => 0];
        }

        $size = $this->storageClient->size($video->storage_key);

        if ($size > VideoStorageClient::MaxSizeBytes) {
            return ['valid' => false, 'size' => $size];
        }

        $mimeType = $this->storageClient->mimeType($video->storage_key);

        if ($mimeType !== null && VideoMimeType::isAllowed($mimeType)) {
            return ['valid' => true, 'size' => $size];
        }

        // Object stores often report QuickTime/MOV as application/octet-stream.
        // The mime was already whitelist-validated when the upload URL was issued.
        if (
            ($mimeType === null || $mimeType === 'application/octet-stream')
            && VideoMimeType::isAllowed($video->mime_type)
        ) {
            return ['valid' => true, 'size' => $size];
        }

        return ['valid' => false, 'size' => $size];
    }

    private function rejectPendingVideo(Video $video): void
    {
        $this->storageClient->delete($video->storage_key);
        $video->forceDelete();
    }
}
