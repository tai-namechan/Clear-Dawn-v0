<?php

namespace App\Services;

use App\Exceptions\VideoStorageNotConfiguredException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class VideoStorageClient
{
    public const int MaxSizeBytes = 104_857_600;

    private readonly Filesystem $disk;

    public function __construct()
    {
        $this->disk = Storage::disk((string) config('filesystems.videos', 'videos'));
    }

    /**
     * @return array{url: string, headers: array<string, string>, expires_at: string}
     */
    public function temporaryUploadUrl(string $key, int $expiryMinutes, string $contentType): array
    {
        $this->assertConfigured();

        $expiresAt = Carbon::now()->addMinutes($expiryMinutes);

        /** @var array{url: string, headers: array<string, string>} $result */
        $result = $this->disk->temporaryUploadUrl($key, $expiresAt, [
            'ContentType' => $contentType,
        ]);

        return [
            'url' => $result['url'],
            'headers' => $result['headers'],
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * @return array{url: string, expires_at: string}
     */
    public function temporaryUrl(string $key, int $expiryMinutes): array
    {
        $this->assertConfigured();

        $expiresAt = Carbon::now()->addMinutes($expiryMinutes);

        return [
            'url' => $this->disk->temporaryUrl($key, $expiresAt),
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function exists(string $key): bool
    {
        return $this->disk->exists($key);
    }

    public function size(string $key): int
    {
        return $this->disk->size($key);
    }

    public function mimeType(string $key): ?string
    {
        $mimeType = $this->disk->mimeType($key);

        return $mimeType === false ? null : $mimeType;
    }

    public function delete(string $key): void
    {
        $this->disk->delete($key);
    }

    /**
     * Presigned URL 発行前に必須設定を確認する。
     * 未設定だと AWS SDK が "Bucket" 空で InvalidArgumentException → 500 になる。
     */
    private function assertConfigured(): void
    {
        $diskName = (string) config('filesystems.videos', 'videos');
        /** @var array<string, mixed> $config */
        $config = config("filesystems.disks.{$diskName}", []);

        $bucket = trim((string) ($config['bucket'] ?? ''));

        if ($bucket === '') {
            throw VideoStorageNotConfiguredException::missingBucket();
        }
    }
}
