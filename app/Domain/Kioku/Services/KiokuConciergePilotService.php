<?php

namespace App\Domain\Kioku\Services;

use App\Domain\Kioku\Exceptions\KiokuLetterException;
use App\Domain\Kioku\KiokuConciergeScheduleState;
use App\Domain\Kioku\KiokuLetterCadence;
use App\Domain\Kioku\KiokuLetterMode;
use App\Domain\Kioku\Models\KiokuConciergeSchedule;
use App\Domain\Kioku\Models\KiokuLetter;
use App\Domain\Kioku\Models\KiokuLetterItem;
use App\Domain\Kioku\Models\Memory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Daily pilot schedule lifecycle and due delivery
 * (docs/product/kioku-concierge-daily-pilot.md).
 */
final class KiokuConciergePilotService
{
    public const UNREAD_STREAK_PAUSE = 3;

    public function __construct(
        private KiokuLetterGenerator $generator,
        private KiokuLetterHaltGuard $haltGuard,
    ) {}

    /**
     * @return array{schedule: KiokuConciergeSchedule, letter: KiokuLetter|null}
     */
    public function start(
        User $user,
        CarbonImmutable $startDate,
        int $days,
        string $time,
        string $timezone,
        bool $sendNow,
        bool $dryRun,
    ): array {
        if ($days < 1 || $days > 31) {
            throw new KiokuLetterException('--days must be between 1 and 31.');
        }

        if (! preg_match('/^\d{2}:\d{2}$/', $time)) {
            throw new KiokuLetterException('--time must be HH:MM (e.g. 21:00).');
        }

        $this->haltGuard->assertGenerationAllowed((int) $user->id);

        // Interpret the calendar start date in the schedule timezone (not app TZ).
        $startDate = CarbonImmutable::parse($startDate->toDateString(), $timezone)->startOfDay();
        $endDate = $startDate->addDays($days - 1);

        $draft = new KiokuConciergeSchedule([
            'user_id' => $user->id,
            'state' => KiokuConciergeScheduleState::Active->value,
            'pilot_start_date' => $startDate->toDateString(),
            'pilot_end_date' => $endDate->toDateString(),
            'pilot_days' => $days,
            'timezone' => $timezone,
            'daily_delivery_time' => $time,
        ]);

        // First slot = pilot start date @ daily_delivery_time in schedule TZ → UTC.
        $nextDelivery = $this->computeNextDeliveryAt(
            $draft,
            CarbonImmutable::now('UTC'),
            $startDate,
        );

        if ($nextDelivery === null) {
            throw new KiokuLetterException('Pilot window has no deliverable slot.');
        }

        if ($dryRun) {
            $draft->next_delivery_at = $nextDelivery;

            return ['schedule' => $draft, 'letter' => null];
        }

        $schedule = KiokuConciergeSchedule::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'state' => KiokuConciergeScheduleState::Active->value,
                'pilot_start_date' => $startDate->toDateString(),
                'pilot_end_date' => $endDate->toDateString(),
                'pilot_days' => $days,
                'timezone' => $timezone,
                'daily_delivery_time' => $time,
                'next_delivery_at' => $nextDelivery,
                'consecutive_unopened' => 0,
                'pause_reason' => null,
            ],
        );

        $letter = null;
        if ($sendNow) {
            $letter = $this->deliverForSchedule(
                $schedule,
                forceLocalDate: CarbonImmutable::now($timezone)->startOfDay(),
            );
        }

        return ['schedule' => $schedule->refresh(), 'letter' => $letter];
    }

    public function pause(User $user, string $note): KiokuConciergeSchedule
    {
        $schedule = $this->scheduleFor($user);
        if (! in_array($schedule->stateEnum(), [KiokuConciergeScheduleState::Active, KiokuConciergeScheduleState::Paused], true)) {
            throw new KiokuLetterException("Schedule cannot be paused from state [{$schedule->state}].");
        }

        $schedule->transitionTo(KiokuConciergeScheduleState::Paused, trim($note) !== '' ? trim($note) : 'manual pause');
        $schedule->forceFill(['next_delivery_at' => null])->save();

        return $schedule->refresh();
    }

    public function resume(User $user, string $note): KiokuConciergeSchedule
    {
        $schedule = $this->scheduleFor($user);
        if ($schedule->stateEnum() !== KiokuConciergeScheduleState::Paused) {
            throw new KiokuLetterException("Schedule cannot be resumed from state [{$schedule->state}].");
        }

        if ($this->haltGuard->hasUnresolvedHalt((int) $user->id)) {
            throw new KiokuLetterException('Unresolved sensitive_leak halt blocks resume. Run resolve-halt first.');
        }

        return $this->activateForNextDelivery(
            $schedule,
            trim($note) !== '' ? 'resumed: '.trim($note) : null,
        );
    }

    /**
     * Shared resume path for manual resume and halt resolve.
     * No past-day backfill: next slot is today (if before delivery time) or tomorrow.
     * Past pilot end / next slot beyond end → completed with next_delivery_at NULL.
     */
    public function activateForNextDelivery(
        KiokuConciergeSchedule $schedule,
        ?string $activeReason = null,
    ): KiokuConciergeSchedule {
        if ($schedule->pilot_end_date !== null
            && CarbonImmutable::now($schedule->timezone)->toDateString() > $schedule->pilot_end_date->toDateString()
        ) {
            $schedule->transitionTo(KiokuConciergeScheduleState::Completed, 'pilot ended');
            $schedule->forceFill(['next_delivery_at' => null])->save();

            return $schedule->refresh();
        }

        $next = $this->computeNextDeliveryAt($schedule, CarbonImmutable::now('UTC'));
        if ($next === null) {
            $schedule->transitionTo(KiokuConciergeScheduleState::Completed, 'pilot window ended');
            $schedule->forceFill(['next_delivery_at' => null])->save();

            return $schedule->refresh();
        }

        $schedule->transitionTo(KiokuConciergeScheduleState::Active, $activeReason);
        $schedule->forceFill([
            'next_delivery_at' => $next,
            'consecutive_unopened' => 0,
        ])->save();

        return $schedule->refresh();
    }

    /**
     * Deliver today's letter if due. Never backfills missed past days.
     * Schedule checks run in a short lock; the AI call stays outside so
     * we do not hold row locks across network I/O.
     */
    public function deliverForSchedule(
        KiokuConciergeSchedule $schedule,
        ?CarbonImmutable $forceLocalDate = null,
    ): ?KiokuLetter {
        $plan = DB::transaction(function () use ($schedule, $forceLocalDate): ?array {
            /** @var KiokuConciergeSchedule $locked */
            $locked = KiokuConciergeSchedule::query()
                ->withoutUserScope()
                ->whereKey($schedule->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->stateEnum()->canGenerate()) {
                return null;
            }

            $this->haltGuard->assertGenerationAllowed((int) $locked->user_id);

            $nowUtc = CarbonImmutable::now('UTC');
            $localNow = $nowUtc->timezone($locked->timezone);
            $localDate = ($forceLocalDate ?? $localNow)->startOfDay();

            if (! $locked->isWithinPilot($localDate)) {
                if ($locked->pilot_end_date !== null
                    && $localDate->toDateString() > $locked->pilot_end_date->toDateString()
                ) {
                    $locked->transitionTo(KiokuConciergeScheduleState::Completed, 'pilot window ended');
                    $locked->forceFill(['next_delivery_at' => null])->save();
                }

                return null;
            }

            // No past-day backfill: only today's local date (unless --send-now).
            if ($forceLocalDate === null && $localDate->toDateString() !== $localNow->toDateString()) {
                return null;
            }

            if ($this->shouldPauseForUnreadStreak($locked)) {
                $locked->transitionTo(
                    KiokuConciergeScheduleState::Paused,
                    '3 consecutive unread live daily letters',
                );
                $locked->forceFill([
                    'consecutive_unopened' => self::UNREAD_STREAK_PAUSE,
                    'next_delivery_at' => null,
                ])->save();

                return null;
            }

            $dedupeKey = KiokuLetterCadence::Daily->dedupeKeyFor($localDate);
            $existing = KiokuLetter::query()
                ->withoutUserScope()
                ->where('user_id', $locked->user_id)
                ->where('dedupe_key', $dedupeKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $this->advanceNextDelivery($locked, $localDate);

                return ['existing' => $existing, 'local_date' => $localDate, 'pilot_day' => null, 'schedule_id' => $locked->id];
            }

            return [
                'existing' => null,
                'local_date' => $localDate,
                'pilot_day' => $locked->pilotDayFor($localDate),
                'schedule_id' => $locked->id,
                'user_id' => $locked->user_id,
            ];
        });

        if ($plan === null) {
            return null;
        }

        if ($plan['existing'] instanceof KiokuLetter) {
            return $plan['existing'];
        }

        $user = User::query()->findOrFail($plan['user_id']);
        $character = (string) config('kioku.concierge.default_character', 'shiori');
        /** @var CarbonImmutable $localDate */
        $localDate = $plan['local_date'];

        $letter = $this->generator->generateLetter(
            user: $user,
            characterVariant: $character,
            context: null,
            mode: KiokuLetterMode::Live,
            cadence: KiokuLetterCadence::Daily,
            deliveryDate: $localDate,
            pilotDay: $plan['pilot_day'],
        );

        DB::transaction(function () use ($plan, $localDate): void {
            /** @var KiokuConciergeSchedule $locked */
            $locked = KiokuConciergeSchedule::query()
                ->withoutUserScope()
                ->whereKey($plan['schedule_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->advanceNextDelivery($locked, $localDate);

            if ($locked->pilot_end_date !== null
                && $localDate->toDateString() >= $locked->pilot_end_date->toDateString()
                && $locked->stateEnum() === KiokuConciergeScheduleState::Active
            ) {
                $locked->transitionTo(KiokuConciergeScheduleState::Completed, 'final pilot day delivered');
                $locked->forceFill(['next_delivery_at' => null])->save();
            }
        });

        return $letter;
    }

    /**
     * @return Collection<int, KiokuConciergeSchedule>
     */
    public function dueSchedules(CarbonImmutable $nowUtc): Collection
    {
        return KiokuConciergeSchedule::query()
            ->withoutUserScope()
            ->where('state', KiokuConciergeScheduleState::Active->value)
            ->whereNotNull('next_delivery_at')
            ->where('next_delivery_at', '<=', $nowUtc)
            ->orderBy('next_delivery_at')
            ->limit(100)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function report(User $user): array
    {
        $schedule = KiokuConciergeSchedule::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->first();

        $letters = KiokuLetter::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->where('mode', KiokuLetterMode::Live->value)
            ->where('cadence', KiokuLetterCadence::Daily->value)
            ->whereNotIn('status', [KiokuLetter::STATUS_GENERATING, KiokuLetter::STATUS_FAILED])
            ->with('items')
            ->orderBy('delivery_date')
            ->get();

        $generated = $letters->count();
        $openedWithin24h = $letters->filter(function (KiokuLetter $letter): bool {
            return $letter->opened_at !== null
                && $letter->published_at !== null
                && $letter->opened_at->lessThanOrEqualTo($letter->published_at->copy()->addDay());
        })->count();

        $allItems = $letters->flatMap->items;
        $judged = $allItems->whereNotNull('verdict');
        $hits = $judged->where('verdict', KiokuLetterItem::VERDICT_HIT)->count();
        $softHits = $judged->where('verdict', KiokuLetterItem::VERDICT_SOFT_HIT)->count();
        $leaks = $judged->where('verdict', KiokuLetterItem::VERDICT_SENSITIVE_LEAK)->count();
        $judgedCount = $judged->count();

        $emptyDays = $letters->filter(fn (KiokuLetter $l) => (int) $l->item_count === 0)->count();

        $maxStreak = $this->maxConsecutiveUnread($letters);

        $pilotDays = $schedule === null ? 14 : $schedule->pilot_days;
        $start = $schedule?->pilot_start_date !== null
            ? CarbonImmutable::parse($schedule->pilot_start_date->toDateString())
            : null;
        $end = $schedule?->pilot_end_date !== null
            ? CarbonImmutable::parse($schedule->pilot_end_date->toDateString())
            : null;

        $preStart = $start?->subDays(14);
        $memoryStats = [
            'before' => $this->memoryCaptureStats((int) $user->id, $preStart, $start?->subDay()),
            'during' => $this->memoryCaptureStats((int) $user->id, $start, $end),
        ];

        return [
            'state' => $schedule === null ? 'none' : $schedule->state,
            'pause_reason' => $schedule?->pause_reason,
            'pilot_days' => $pilotDays,
            'generated' => $generated,
            'generated_label' => "{$generated} / {$pilotDays}",
            'opened_within_24h' => $openedWithin24h,
            'opened_within_24h_label' => $generated === 0 ? 'N/A' : "{$openedWithin24h} / {$generated}",
            'hit_rate' => $judgedCount === 0 ? null : round($hits / $judgedCount, 4),
            'hit_rate_label' => $judgedCount === 0 ? 'N/A' : (round(($hits / $judgedCount) * 100, 1).'%'),
            'useful_rate' => $judgedCount === 0 ? null : round(($hits + $softHits) / $judgedCount, 4),
            'useful_rate_label' => $judgedCount === 0 ? 'N/A' : (round((($hits + $softHits) / $judgedCount) * 100, 1).'%'),
            'max_consecutive_unopened' => $maxStreak,
            'empty_days' => $emptyDays,
            'sensitive_leak_count' => $leaks,
            'memory_capture' => $memoryStats,
            'target_hit_rate' => 0.25,
            'target_useful_rate' => 0.50,
        ];
    }

    private function scheduleFor(User $user): KiokuConciergeSchedule
    {
        $schedule = KiokuConciergeSchedule::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->first();

        if ($schedule === null) {
            throw new KiokuLetterException("No concierge schedule for user {$user->id}. Run pilot:start first.");
        }

        return $schedule;
    }

    private function shouldPauseForUnreadStreak(KiokuConciergeSchedule $schedule): bool
    {
        $recent = KiokuLetter::query()
            ->withoutUserScope()
            ->where('user_id', $schedule->user_id)
            ->where('mode', KiokuLetterMode::Live->value)
            ->where('cadence', KiokuLetterCadence::Daily->value)
            ->whereNotIn('status', [KiokuLetter::STATUS_GENERATING, KiokuLetter::STATUS_FAILED])
            ->orderByDesc('delivery_date')
            ->limit(self::UNREAD_STREAK_PAUSE)
            ->get();

        if ($recent->count() < self::UNREAD_STREAK_PAUSE) {
            return false;
        }

        $cutoff = now()->subDay();

        return $recent->every(function (KiokuLetter $letter) use ($cutoff): bool {
            if ($letter->published_at === null || $letter->published_at->greaterThan($cutoff)) {
                return false;
            }

            return $letter->opened_at === null;
        });
    }

    private function advanceNextDelivery(KiokuConciergeSchedule $schedule, CarbonImmutable $localDate): void
    {
        if ($schedule->pilot_end_date !== null
            && $localDate->toDateString() >= $schedule->pilot_end_date->toDateString()
        ) {
            $schedule->forceFill(['next_delivery_at' => null])->save();

            return;
        }

        $schedule->forceFill([
            'next_delivery_at' => $this->computeNextDeliveryAt($schedule, CarbonImmutable::now('UTC'), $localDate->addDay()),
        ])->save();
    }

    /**
     * Build the next delivery instant in schedule.timezone, store as UTC.
     * When $fromLocalDate is set, that calendar day is used (start / advance).
     * When null, uses "now" in schedule TZ: before delivery time → today, else tomorrow.
     */
    public function computeNextDeliveryAt(
        KiokuConciergeSchedule $schedule,
        CarbonImmutable $nowUtc,
        ?CarbonImmutable $fromLocalDate = null,
    ): ?CarbonImmutable {
        if ($schedule->pilot_end_date === null) {
            return null;
        }

        [$hour, $minute] = array_map('intval', explode(':', $schedule->daily_delivery_time));
        $tz = $schedule->timezone;
        $localNow = $nowUtc->timezone($tz);

        if ($fromLocalDate !== null) {
            $candidateDay = CarbonImmutable::parse($fromLocalDate->toDateString(), $tz)->startOfDay();
        } else {
            $candidateDay = $localNow->startOfDay();
            if ($localNow->format('H:i') >= $schedule->daily_delivery_time) {
                $candidateDay = $candidateDay->addDay();
            }
        }

        if ($candidateDay->toDateString() > $schedule->pilot_end_date->toDateString()) {
            return null;
        }

        return $candidateDay->setTime($hour, $minute)->utc();
    }

    /**
     * @param  Collection<int, KiokuLetter>  $letters
     */
    private function maxConsecutiveUnread(Collection $letters): int
    {
        $max = 0;
        $current = 0;
        foreach ($letters as $letter) {
            $unread = $letter->opened_at === null
                || ($letter->published_at !== null
                    && $letter->opened_at->greaterThan($letter->published_at->copy()->addDay()));
            if ($unread) {
                $current++;
                $max = max($max, $current);
            } else {
                $current = 0;
            }
        }

        return $max;
    }

    /**
     * @return array{days: int, count: int}
     */
    private function memoryCaptureStats(int $userId, ?CarbonImmutable $from, ?CarbonImmutable $to): array
    {
        if ($from === null || $to === null) {
            return ['days' => 0, 'count' => 0];
        }

        $rows = Memory::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->where('source_type', '!=', 'kioku_letter')
            ->whereDate('captured_at', '>=', $from->toDateString())
            ->whereDate('captured_at', '<=', $to->toDateString())
            ->get(['captured_at']);

        return [
            'days' => $rows->map(fn (Memory $m) => $m->captured_at->toDateString())->unique()->count(),
            'count' => $rows->count(),
        ];
    }
}
