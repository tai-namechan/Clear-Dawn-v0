<?php

namespace App\Domain\Kioku\Capture\Store;

use App\Domain\Kioku\Capture\CanonicalRawStore;
use App\Domain\Kioku\Capture\Dto\AudioRaw;
use App\Domain\Kioku\Capture\Dto\CapturedRaw;
use App\Domain\Kioku\Capture\Dto\TextRaw;
use App\Domain\Kioku\Capture\Dto\UrlRaw;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Models\MemoryAsset;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Single durable write path for Canonical Raw. Does not dispatch AI jobs.
 */
final class EloquentCanonicalRawStore implements CanonicalRawStore
{
    /**
     * @return array{memory: Memory, created: bool}
     */
    public function persist(CapturedRaw $raw): array
    {
        if ($raw->clientCaptureId !== null) {
            $existing = $this->findByClientCaptureId($raw->userId, $raw->clientCaptureId);
            if ($existing !== null) {
                return ['memory' => $existing, 'created' => false];
            }
        }

        return match (true) {
            $raw instanceof TextRaw, $raw instanceof UrlRaw => $this->persistTextLike($raw),
            $raw instanceof AudioRaw => $this->persistAudio($raw),
            default => throw new InvalidArgumentException('Unsupported CapturedRaw type.'),
        };
    }

    /**
     * @return array{memory: Memory, created: bool}
     */
    private function persistTextLike(TextRaw|UrlRaw $raw): array
    {
        $content = $raw instanceof UrlRaw ? $raw->url : $raw->text;

        try {
            $memory = Memory::query()->create([
                'user_id' => $raw->userId,
                'client_capture_id' => $raw->clientCaptureId,
                'source_type' => $raw->legacySourceType(),
                'raw_kind' => $raw->rawKind->value,
                'capture_channel' => $raw->captureChannel->value,
                'memory_type' => null,
                'title' => '整理中…',
                'raw_content' => $content,
                'captured_at' => $raw->capturedAt,
                'sensitive' => $raw->sensitive,
                'status' => 'captured',
            ]);
        } catch (UniqueConstraintViolationException) {
            return [
                'memory' => $this->findExistingOrFail($raw->userId, (string) $raw->clientCaptureId),
                'created' => false,
            ];
        }

        return ['memory' => $memory, 'created' => true];
    }

    /**
     * @return array{memory: Memory, created: bool}
     */
    private function persistAudio(AudioRaw $raw): array
    {
        if ($raw->clientCaptureId === null) {
            throw new InvalidArgumentException('Audio capture requires client_capture_id.');
        }

        $disk = (string) config('kioku.audio.disk');
        $extension = $raw->uploadedFile->guessExtension() ?: 'bin';
        $path = $raw->uploadedFile->storeAs(
            'kioku-audio/'.$raw->userId,
            Str::ulid().'.'.$extension,
            ['disk' => $disk],
        );

        if ($path === false) {
            throw new RuntimeException('Failed to persist audio original.');
        }

        try {
            $memory = DB::transaction(function () use ($raw, $disk, $path): Memory {
                $memory = Memory::query()->create([
                    'user_id' => $raw->userId,
                    'client_capture_id' => $raw->clientCaptureId,
                    'source_type' => $raw->legacySourceType(),
                    'raw_kind' => $raw->rawKind->value,
                    'capture_channel' => $raw->captureChannel->value,
                    'memory_type' => null,
                    'title' => '整理中…',
                    'raw_content' => null,
                    'captured_at' => $raw->capturedAt,
                    'sensitive' => $raw->sensitive,
                    'status' => 'captured',
                    'transcription_status' => 'pending',
                ]);

                MemoryAsset::query()->create([
                    'memory_id' => $memory->id,
                    'kind' => MemoryAsset::KIND_AUDIO_ORIGINAL,
                    'disk' => $disk,
                    'path' => $path,
                    'mime_type' => $raw->serverDetectedMime,
                    'byte_size' => (int) $raw->uploadedFile->getSize(),
                    'duration_ms' => $raw->declaredDurationMs,
                    'checksum' => hash_file('sha256', $raw->uploadedFile->getRealPath()) ?: null,
                ]);

                return $memory;
            });
        } catch (UniqueConstraintViolationException) {
            Storage::disk($disk)->delete($path);

            return [
                'memory' => $this->findExistingOrFail($raw->userId, $raw->clientCaptureId),
                'created' => false,
            ];
        } catch (Throwable $e) {
            Storage::disk($disk)->delete($path);

            throw $e;
        }

        return ['memory' => $memory, 'created' => true];
    }

    private function findExistingOrFail(int $userId, string $clientCaptureId): Memory
    {
        $memory = $this->findByClientCaptureId($userId, $clientCaptureId);
        if ($memory === null) {
            throw new RuntimeException('Duplicate capture detected but original memory not found.');
        }

        return $memory;
    }

    private function findByClientCaptureId(int $userId, string $clientCaptureId): ?Memory
    {
        return Memory::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->where('client_capture_id', $clientCaptureId)
            ->first();
    }
}
