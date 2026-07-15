<?php

namespace App\Domain\Kioku\Jobs;

use App\Domain\Kioku\Exceptions\KiokuLetterException;
use App\Domain\Kioku\Models\KiokuConciergeSchedule;
use App\Domain\Kioku\Services\KiokuConciergePilotService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One unique daily delivery attempt per schedule
 * (docs/product/kioku-concierge-daily-pilot.md §8.2).
 */
class GenerateDailyKiokuLetterJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public int $uniqueFor = 600;

    public function __construct(public string $scheduleId) {}

    public function uniqueId(): string
    {
        return $this->scheduleId;
    }

    public function handle(KiokuConciergePilotService $pilot): void
    {
        $schedule = KiokuConciergeSchedule::query()
            ->withoutUserScope()
            ->find($this->scheduleId);

        if ($schedule === null) {
            return;
        }

        try {
            $pilot->deliverForSchedule($schedule);
        } catch (KiokuLetterException $e) {
            Log::warning('kioku.concierge.daily_delivery_skipped', [
                'schedule_id' => $this->scheduleId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('kioku.concierge.daily_delivery_failed', [
            'schedule_id' => $this->scheduleId,
            'message' => $exception?->getMessage(),
        ]);
    }
}
