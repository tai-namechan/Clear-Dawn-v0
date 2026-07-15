<?php

namespace App\Domain\Kioku\Services;

use App\Domain\Kioku\Models\Memory;
use Illuminate\Support\Facades\DB;

/**
 * Records that memories were surfaced to the human.
 *
 * - last_referenced_at / referenced_count: first letter open (live only)
 * - last_delivered_at: live letter published/empty confirm (even if unread)
 *
 * Both feed the 14-day candidate cooldown. Query-builder updates only —
 * raw_content stays out of reach by construction.
 */
final class MemoryReferenceService
{
    /**
     * @param  array<string>  $memoryIds
     */
    public function markReferenced(array $memoryIds): void
    {
        if ($memoryIds === []) {
            return;
        }

        Memory::query()
            ->withoutUserScope()
            ->whereIn('id', $memoryIds)
            ->update([
                'referenced_count' => DB::raw('referenced_count + 1'),
                'last_referenced_at' => now(),
            ]);
    }

    /**
     * @param  array<string>  $memoryIds
     */
    public function markDelivered(array $memoryIds): void
    {
        if ($memoryIds === []) {
            return;
        }

        Memory::query()
            ->withoutUserScope()
            ->whereIn('id', $memoryIds)
            ->update([
                'last_delivered_at' => now(),
            ]);
    }
}
