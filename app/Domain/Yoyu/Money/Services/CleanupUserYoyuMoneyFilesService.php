<?php

namespace App\Domain\Yoyu\Money\Services;

use App\Domain\Yoyu\Money\Models\MoneyImport;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Deletes Yoyu Money CSV import originals from private storage before
 * the user row (and cascaded import rows) goes away.
 */
final class CleanupUserYoyuMoneyFilesService
{
    private const DISK = 'local';

    private const PATH_PREFIX = 'yoyu-money-imports';

    /**
     * Must be called before User::delete(). Failures abort account deletion
     * so orphan import files are never left behind silently.
     */
    public function deleteForUser(User $user): void
    {
        $failures = [];

        $imports = MoneyImport::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->whereNotNull('source_storage_path')
            ->get(['id', 'source_storage_path']);

        $knownPaths = [];

        foreach ($imports as $import) {
            $path = $import->source_storage_path;
            if ($path === null || $path === '') {
                continue;
            }

            $knownPaths[$path] = true;

            try {
                $this->deletePath(self::DISK, $path);
            } catch (Throwable $e) {
                $failures[] = self::DISK.':'.$path.' ('.$e->getMessage().')';
            }
        }

        $prefix = self::PATH_PREFIX.'/'.$user->id;

        try {
            $files = Storage::disk(self::DISK)->allFiles($prefix);
        } catch (Throwable $e) {
            $failures[] = self::DISK.':list '.$prefix.' ('.$e->getMessage().')';
            $files = [];
        }

        foreach ($files as $path) {
            if (isset($knownPaths[$path])) {
                continue;
            }

            try {
                $this->deletePath(self::DISK, $path);
            } catch (Throwable $e) {
                $failures[] = self::DISK.':'.$path.' ('.$e->getMessage().')';
            }
        }

        if ($failures !== []) {
            throw new RuntimeException(
                'Failed to delete Yoyu Money import files before account deletion: '
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
