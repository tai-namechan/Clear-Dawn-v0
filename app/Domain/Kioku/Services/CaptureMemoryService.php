<?php

namespace App\Domain\Kioku\Services;

use App\Domain\Kioku\Jobs\EnrichMemoryJob;
use App\Domain\Kioku\Jobs\TranscribeMemoryAudioJob;
use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Models\MemoryAsset;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Single write path for quick captures, shared by the legacy Inertia store
 * and the JSON capture endpoints. Idempotent on (user_id, client_capture_id)
 * so the client-side queue can resend safely.
 */
final class CaptureMemoryService
{
    /**
     * @return array{memory: Memory, created: bool}
     */
    public function captureText(
        User $user,
        string $rawContent,
        string $sourceType = 'manual',
        ?string $clientCaptureId = null,
        ?string $capturedAt = null,
        bool $sensitive = false,
    ): array {
        $existing = $this->findByClientCaptureId($user, $clientCaptureId);
        if ($existing !== null) {
            return ['memory' => $existing, 'created' => false];
        }

        $content = trim($rawContent);
        if ($sourceType === 'manual' && filter_var($content, FILTER_VALIDATE_URL)) {
            $sourceType = 'url';
        }

        try {
            $memory = Memory::query()->create([
                'user_id' => $user->id,
                'client_capture_id' => $clientCaptureId,
                'source_type' => $sourceType,
                'memory_type' => null,
                'title' => '整理中…',
                'raw_content' => $content,
                'captured_at' => $capturedAt ?? now(),
                'sensitive' => $sensitive,
                'status' => 'captured',
            ]);
        } catch (UniqueConstraintViolationException) {
            return ['memory' => $this->findExistingOrFail($user, $clientCaptureId), 'created' => false];
        }

        EnrichMemoryJob::dispatch($memory->id)->afterCommit();

        return ['memory' => $memory, 'created' => true];
    }

    /**
     * Voice capture: the audio original is the canonical raw, so the file is
     * persisted to the private disk first and the Memory + Asset rows are
     * created in one transaction. Transcription/enrichment never run before
     * the raw is durable, and a failed transaction removes the orphan file.
     *
     * @return array{memory: Memory, created: bool}
     */
    public function captureVoice(
        User $user,
        UploadedFile $audio,
        string $clientCaptureId,
        int $durationMs,
        ?string $capturedAt = null,
        bool $sensitive = false,
    ): array {
        $existing = $this->findByClientCaptureId($user, $clientCaptureId);
        if ($existing !== null) {
            return ['memory' => $existing, 'created' => false];
        }

        $disk = (string) config('kioku.audio.disk');
        $extension = $audio->guessExtension() ?: 'bin';
        $path = $audio->storeAs(
            'kioku-audio/'.$user->id,
            Str::ulid().'.'.$extension,
            ['disk' => $disk],
        );

        if ($path === false) {
            throw new RuntimeException('Failed to persist audio original.');
        }

        try {
            $memory = DB::transaction(function () use ($user, $audio, $clientCaptureId, $durationMs, $capturedAt, $sensitive, $disk, $path): Memory {
                $memory = Memory::query()->create([
                    'user_id' => $user->id,
                    'client_capture_id' => $clientCaptureId,
                    'source_type' => 'voice',
                    'memory_type' => null,
                    'title' => '整理中…',
                    'raw_content' => null,
                    'captured_at' => $capturedAt ?? now(),
                    'sensitive' => $sensitive,
                    'status' => 'captured',
                    'transcription_status' => 'pending',
                ]);

                MemoryAsset::query()->create([
                    'memory_id' => $memory->id,
                    'kind' => MemoryAsset::KIND_AUDIO_ORIGINAL,
                    'disk' => $disk,
                    'path' => $path,
                    'mime_type' => $audio->getMimeType() ?? 'application/octet-stream',
                    'byte_size' => (int) $audio->getSize(),
                    'duration_ms' => $durationMs,
                    'checksum' => hash_file('sha256', $audio->getRealPath()) ?: null,
                ]);

                return $memory;
            });
        } catch (UniqueConstraintViolationException) {
            Storage::disk($disk)->delete($path);

            return ['memory' => $this->findExistingOrFail($user, $clientCaptureId), 'created' => false];
        } catch (Throwable $e) {
            Storage::disk($disk)->delete($path);

            throw $e;
        }

        $this->dispatchTranscriptionIfConfigured($memory);

        return ['memory' => $memory, 'created' => true];
    }

    /**
     * With no provider configured the memory stays transcription_status
     * 'pending' and the UI reports transcription as not configured —
     * the raw audio is already durable either way.
     */
    private function dispatchTranscriptionIfConfigured(Memory $memory): void
    {
        if (config('kioku.transcription.provider', 'none') === 'none') {
            return;
        }

        TranscribeMemoryAudioJob::dispatch($memory->id)->afterCommit();
    }

    private function findExistingOrFail(User $user, ?string $clientCaptureId): Memory
    {
        $memory = $this->findByClientCaptureId($user, $clientCaptureId);
        if ($memory === null) {
            throw new RuntimeException('Duplicate capture detected but original memory not found.');
        }

        return $memory;
    }

    private function findByClientCaptureId(User $user, ?string $clientCaptureId): ?Memory
    {
        if ($clientCaptureId === null) {
            return null;
        }

        return Memory::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->where('client_capture_id', $clientCaptureId)
            ->first();
    }
}
