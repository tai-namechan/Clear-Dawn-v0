<?php

namespace App\Domain\Kioku\Services;

use App\Domain\Kioku\Exceptions\KiokuLetterException;
use App\Domain\Kioku\KiokuConciergeScheduleState;
use App\Domain\Kioku\Models\KiokuConciergeSchedule;
use App\Domain\Kioku\Models\KiokuLetter;
use App\Domain\Kioku\Models\KiokuLetterItem;
use App\Domain\Kioku\Models\Memory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Operator resolve for unresolved sensitive_leak halts.
 * Never auto-clears Memory.sensitive — that requires a separate audited repair.
 */
final class KiokuLetterHaltResolveService
{
    public function resolve(User $user, KiokuLetter $letter, string $note): KiokuLetter
    {
        $note = trim($note);
        if ($note === '') {
            throw new KiokuLetterException('--note is required when resolving a halt.');
        }

        if ((int) $letter->user_id !== (int) $user->id) {
            throw new KiokuLetterException('Letter does not belong to the given user.');
        }

        return DB::transaction(function () use ($user, $letter, $note): KiokuLetter {
            /** @var KiokuLetter $locked */
            $locked = KiokuLetter::query()
                ->withoutUserScope()
                ->whereKey($letter->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $locked->user_id !== (int) $user->id) {
                throw new KiokuLetterException('Letter does not belong to the given user.');
            }

            // Idempotent: already resolved.
            if ($locked->status === KiokuLetter::STATUS_HALTED && $locked->halt_resolved_at !== null) {
                return $locked;
            }

            if ($locked->status !== KiokuLetter::STATUS_HALTED) {
                throw new KiokuLetterException("Letter {$locked->id} is not an unresolved halt.");
            }

            $leakItems = $locked->items()
                ->where('verdict', KiokuLetterItem::VERDICT_SENSITIVE_LEAK)
                ->lockForUpdate()
                ->get();

            foreach ($leakItems as $item) {
                $memory = Memory::query()
                    ->withoutUserScope()
                    ->whereKey($item->memory_id)
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                if ($memory === null || ! $memory->sensitive) {
                    throw new KiokuLetterException(
                        "Memory {$item->memory_id} from a sensitive_leak item must remain sensitive=true before resolve."
                    );
                }
            }

            $locked->update([
                'halt_resolved_at' => now(),
                'halt_resolution_note' => $note,
            ]);

            $this->maybeResumeSchedule((int) $user->id);

            return $locked->refresh();
        });
    }

    private function maybeResumeSchedule(int $userId): void
    {
        /** @var KiokuConciergeSchedule|null $schedule */
        $schedule = KiokuConciergeSchedule::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->first();

        if ($schedule === null || $schedule->stateEnum() !== KiokuConciergeScheduleState::Halted) {
            return;
        }

        if ($schedule->pilot_end_date !== null
            && CarbonImmutable::now($schedule->timezone)->toDateString() > $schedule->pilot_end_date->toDateString()
        ) {
            $schedule->transitionTo(KiokuConciergeScheduleState::Completed, 'pilot ended before halt resolve');

            return;
        }

        // Only resume when the sole pause reason was sensitive_leak.
        if ($schedule->pause_reason !== null && $schedule->pause_reason !== 'sensitive_leak') {
            $schedule->transitionTo(KiokuConciergeScheduleState::Paused, $schedule->pause_reason);

            return;
        }

        $schedule->transitionTo(KiokuConciergeScheduleState::Active, null);
    }
}
