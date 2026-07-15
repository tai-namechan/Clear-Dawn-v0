<?php

namespace App\Console\Commands;

use App\Domain\Kioku\Jobs\GenerateDailyKiokuLetterJob;
use App\Domain\Kioku\Services\KiokuConciergePilotService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Minute dispatcher for due daily pilot schedules.
 * Dispatches unique jobs; does not generate inline (queue must be running).
 */
class DispatchDueKiokuConciergeLettersCommand extends Command
{
    protected $signature = 'kioku:letters:pilot:dispatch-due';

    protected $description = 'Dispatch unique jobs for due Kioku daily pilot schedules';

    public function handle(KiokuConciergePilotService $pilot): int
    {
        if (! config('kioku.concierge.enabled')) {
            return self::SUCCESS;
        }

        $due = $pilot->dueSchedules(CarbonImmutable::now('UTC'));
        foreach ($due as $schedule) {
            GenerateDailyKiokuLetterJob::dispatch($schedule->id);
        }

        if ($due->isNotEmpty()) {
            $this->info("Dispatched {$due->count()} daily letter job(s).");
        }

        return self::SUCCESS;
    }
}
