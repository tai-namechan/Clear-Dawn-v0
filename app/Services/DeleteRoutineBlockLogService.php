<?php

namespace App\Services;

use App\Models\RoutineBlockLog;
use Illuminate\Support\Facades\DB;

class DeleteRoutineBlockLogService
{
    /**
     * セット記録を取り消し、以降の block_number を詰めて連番を保つ。
     */
    public function handle(RoutineBlockLog $blockLog): void
    {
        DB::transaction(function () use ($blockLog): void {
            $sessionStep = $blockLog->routineSessionStep()->lockForUpdate()->firstOrFail();
            $deletedNumber = (int) $blockLog->block_number;

            $blockLog->delete();

            $sessionStep->blockLogs()
                ->where('block_number', '>', $deletedNumber)
                ->orderBy('block_number')
                ->get()
                ->each(function (RoutineBlockLog $log): void {
                    $log->update([
                        'block_number' => $log->block_number - 1,
                    ]);
                });
        });
    }
}
