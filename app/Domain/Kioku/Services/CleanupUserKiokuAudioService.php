<?php

namespace App\Domain\Kioku\Services;

use App\Domain\Kioku\Models\Memory;
use App\Domain\Kioku\Models\MemoryAsset;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Deletes every Kioku audio original belonging to a user from private storage
 * before the user row (and its cascaded Memory / MemoryAsset rows) goes away.
 *
 * FK cascade alone removes DB rows without firing Eloquent deleted events, so
 * storage cleanup must run explicitly while asset disk/path metadata still
 * exists (docs/product/kioku-quick-capture.md §11).
 */
final class CleanupUserKiokuAudioService
{
    /**
     * Must be called before User::delete(). Failures abort account deletion
     * so orphan audio originals are never left behind silently.
     */
    public function deleteForUser(User $user): void
    {
        $failures = [];

        $assets = MemoryAsset::query()
            ->where('kind', MemoryAsset::KIND_AUDIO_ORIGINAL)
            ->whereIn(
                'memory_id',
                Memory::query()
                    ->withoutUserScope()
                    ->where('user_id', $user->id)
                    ->select('id'),
            )
            ->get(['id', 'disk', 'path']);

        $knownPathsByDisk = [];

        foreach ($assets as $asset) {
            $knownPathsByDisk[$asset->disk][$asset->path] = true;

            try {
                $this->deletePath($asset->disk, $asset->path);
            } catch (Throwable $e) {
                $failures[] = "{$asset->disk}:{$asset->path} ({$e->getMessage()})";
            }
        }

        $prefix = 'kioku-audio/'.$user->id;
        $disks = collect(array_keys($knownPathsByDisk))
            ->push((string) config('kioku.audio.disk'))
            ->unique()
            ->values();

        foreach ($disks as $disk) {
            try {
                $files = Storage::disk($disk)->allFiles($prefix);
            } catch (Throwable $e) {
                $failures[] = "{$disk}:list {$prefix} ({$e->getMessage()})";

                continue;
            }

            foreach ($files as $path) {
                if (isset($knownPathsByDisk[$disk][$path])) {
                    continue;
                }

                try {
                    $this->deletePath($disk, $path);
                } catch (Throwable $e) {
                    $failures[] = "{$disk}:{$path} ({$e->getMessage()})";
                }
            }
        }

        if ($failures !== []) {
            throw new RuntimeException(
                'Failed to delete Kioku audio originals before account deletion: '
                .implode('; ', $failures),
            );
        }
    }

    private function deletePath(string $disk, string $path): void
    {
        $filesystem = Storage::disk($disk);

        if (! $filesystem->exists($path)) {
            return;
        }

        if ($filesystem->delete($path) !== true && $filesystem->exists($path)) {
            throw new RuntimeException("Storage::delete returned false for {$disk}:{$path}");
        }
    }
}
