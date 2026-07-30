<?php

namespace App\Domain\Kioku\Audio;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Server-side audio duration measurement. Client-declared duration_ms is never
 * trusted for billing reservations (PR #213 audit High-1).
 *
 * WAV is parsed from the RIFF header without extra packages. Other containers
 * return null so callers reserve the channel maximum conservatively.
 */
final class AudioDurationProbe
{
    public function fromUploadedFile(UploadedFile $file): ?int
    {
        $path = $file->getRealPath();
        if ($path === false || $path === '') {
            return null;
        }

        return $this->fromLocalPath($path, (string) ($file->getMimeType() ?? ''));
    }

    public function fromMemoryAsset(string $disk, string $path, ?string $mimeType = null): ?int
    {
        try {
            $absolute = Storage::disk($disk)->path($path);
        } catch (\Throwable) {
            return null;
        }

        if (! is_string($absolute) || $absolute === '' || ! is_file($absolute)) {
            return null;
        }

        return $this->fromLocalPath($absolute, (string) ($mimeType ?? ''));
    }

    public function fromLocalPath(string $absolutePath, string $mimeType = ''): ?int
    {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return null;
        }

        if ($this->looksLikeWav($absolutePath, $mimeType)) {
            return $this->probeWav($absolutePath);
        }

        return null;
    }

    private function looksLikeWav(string $path, string $mimeType): bool
    {
        $mime = strtolower($mimeType);
        if (in_array($mime, ['audio/wav', 'audio/x-wav', 'audio/vnd.wave'], true)) {
            return true;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 12);
        fclose($handle);

        return is_string($header)
            && strlen($header) >= 12
            && str_starts_with($header, 'RIFF')
            && substr($header, 8, 4) === 'WAVE';
    }

    private function probeWav(string $path): ?int
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        try {
            $header = fread($handle, 12);
            if (! is_string($header) || strlen($header) < 12 || ! str_starts_with($header, 'RIFF') || substr($header, 8, 4) !== 'WAVE') {
                return null;
            }

            $sampleRate = null;
            $channels = null;
            $bitsPerSample = null;
            $byteRate = null;
            $dataSize = null;

            while (! feof($handle)) {
                $chunkHeader = fread($handle, 8);
                if (! is_string($chunkHeader) || strlen($chunkHeader) < 8) {
                    break;
                }

                $chunkId = substr($chunkHeader, 0, 4);
                $chunkSize = unpack('V', substr($chunkHeader, 4, 4))[1] ?? 0;
                if (! is_int($chunkSize) || $chunkSize < 0) {
                    break;
                }

                if ($chunkId === 'fmt ') {
                    $fmt = fread($handle, min($chunkSize, 16));
                    if (! is_string($fmt) || strlen($fmt) < 16) {
                        return null;
                    }
                    $channels = unpack('v', substr($fmt, 2, 2))[1] ?? null;
                    $sampleRate = unpack('V', substr($fmt, 4, 4))[1] ?? null;
                    $byteRate = unpack('V', substr($fmt, 8, 4))[1] ?? null;
                    $bitsPerSample = unpack('v', substr($fmt, 14, 2))[1] ?? null;
                    if ($chunkSize > 16) {
                        fseek($handle, $chunkSize - 16, SEEK_CUR);
                    }
                } elseif ($chunkId === 'data') {
                    $dataSize = $chunkSize;
                    break;
                } else {
                    fseek($handle, $chunkSize + ($chunkSize % 2), SEEK_CUR);
                }
            }

            if (! is_int($sampleRate) || $sampleRate <= 0 || ! is_int($dataSize) || $dataSize <= 0) {
                return null;
            }

            $bytesPerSecond = null;
            if (is_int($byteRate) && $byteRate > 0) {
                $bytesPerSecond = $byteRate;
            } elseif (
                is_int($channels) && $channels > 0
                && is_int($bitsPerSample) && $bitsPerSample > 0
            ) {
                $bytesPerSecond = (int) ($sampleRate * $channels * ($bitsPerSample / 8));
            }

            if ($bytesPerSecond === null || $bytesPerSecond <= 0) {
                return null;
            }

            return (int) max(1, (int) round(($dataSize / $bytesPerSecond) * 1000));
        } finally {
            fclose($handle);
        }
    }
}
