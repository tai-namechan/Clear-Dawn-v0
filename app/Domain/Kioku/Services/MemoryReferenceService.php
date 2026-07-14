<?php

namespace App\Domain\Kioku\Services;

use App\Domain\Kioku\Models\Memory;
use Illuminate\Support\Facades\DB;

/**
 * Records that memories were surfaced to the human (first letter open).
 * last_referenced_at feeds the letter candidate cooldown so the same memory
 * is not re-delivered within 14 days. Query-builder update only touches
 * counters — raw_content stays out of reach by construction.
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
}
