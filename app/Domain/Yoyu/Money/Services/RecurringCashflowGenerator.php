<?php

namespace App\Domain\Yoyu\Money\Services;

use App\Domain\Yoyu\Money\Enums\MoneyCashflowStatus;
use App\Domain\Yoyu\Money\Enums\MoneyRecurringFrequency;
use App\Domain\Yoyu\Money\Models\MoneyCashflow;
use App\Domain\Yoyu\Money\Models\MoneyRecurringRule;
use App\Domain\Yoyu\Support\UserTimezoneResolver;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class RecurringCashflowGenerator
{
    public function __construct(
        private readonly UserTimezoneResolver $timezoneResolver,
        private readonly MoneySetupService $setupService,
    ) {}

    public function generateForUser(User $user, ?CarbonInterface $through = null): int
    {
        $timezone = $this->timezoneResolver->for($user);
        $today = CarbonImmutable::now($timezone)->startOfDay();

        $settings = $this->setupService->ensureForUser($user);
        $horizonMonths = max(1, (int) ($settings->calculation_horizon_months ?? 3));
        $horizonEnd = $through !== null
            ? CarbonImmutable::instance($through)->timezone($timezone)->startOfDay()
            : $today->addMonthsNoOverflow($horizonMonths)->endOfMonth()->startOfDay();

        $rules = MoneyRecurringRule::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

        $created = 0;

        foreach ($rules as $rule) {
            $created += $this->generateForRule($user, $rule, $horizonEnd, $timezone);
        }

        return $created;
    }

    private function generateForRule(
        User $user,
        MoneyRecurringRule $rule,
        CarbonImmutable $horizonEnd,
        string $timezone,
    ): int {
        // 並行生成（ダッシュボード表示とシミュレーション作成の同時発火など）でも
        // 冪等になるよう、ルール行をロックしてから既存 occurrence を読む
        return (int) DB::transaction(function () use ($user, $rule, $horizonEnd, $timezone): int {
            /** @var MoneyRecurringRule|null $lockedRule */
            $lockedRule = MoneyRecurringRule::query()
                ->withoutUserScope()
                ->whereKey($rule->id)
                ->lockForUpdate()
                ->first();

            if ($lockedRule === null || ! $lockedRule->is_active) {
                return 0;
            }

            $rule = $lockedRule;

            $startOn = CarbonImmutable::parse((string) $rule->start_on->toDateString(), $timezone)->startOfDay();
            $endOn = $rule->end_on !== null
                ? CarbonImmutable::parse((string) $rule->end_on->toDateString(), $timezone)->startOfDay()
                : null;

            $generatedThrough = $rule->generated_through !== null
                ? CarbonImmutable::parse((string) $rule->generated_through->toDateString(), $timezone)->startOfDay()
                : null;

            $from = $generatedThrough !== null
                ? $generatedThrough->addDay()
                : $startOn;

            if ($from->lt($startOn)) {
                $from = $startOn;
            }

            $until = $horizonEnd;
            if ($endOn !== null && $endOn->lt($until)) {
                $until = $endOn;
            }

            if ($from->gt($until)) {
                return 0;
            }

            $existing = MoneyCashflow::query()
                ->withoutUserScope()
                ->where('recurring_rule_id', $rule->id)
                ->whereNotNull('occurrence_on')
                ->pluck('occurrence_on')
                ->map(fn ($date): string => CarbonImmutable::parse((string) $date)->toDateString())
                ->all();
            $existingSet = array_fill_keys($existing, true);

            $occurrences = $this->occurrenceDates($rule, $from, $until, $timezone);
            $created = 0;
            $latest = $generatedThrough;

            foreach ($occurrences as $occurrence) {
                $key = $occurrence->toDateString();
                if (isset($existingSet[$key])) {
                    if ($latest === null || $occurrence->gt($latest)) {
                        $latest = $occurrence;
                    }

                    continue;
                }

                MoneyCashflow::query()->withoutUserScope()->create([
                    'user_id' => $user->id,
                    'direction' => $rule->direction,
                    'kind' => $rule->cashflow_kind,
                    'name' => $rule->name,
                    'amount_minor' => $rule->amount_minor,
                    'currency_code' => $rule->currency_code,
                    'due_on' => $key,
                    'original_due_on' => $key,
                    'status' => MoneyCashflowStatus::Planned,
                    'certainty' => $rule->certainty,
                    'category_id' => $rule->category_id,
                    'counterparty_id' => $rule->counterparty_id,
                    'settlement_account_id' => $rule->settlement_account_id,
                    'credit_card_id' => $rule->credit_card_id,
                    'loan_id' => $rule->loan_id,
                    'payment_method' => $rule->payment_method,
                    'income_amount_basis' => $rule->income_amount_basis,
                    'cost_behavior' => $rule->cost_behavior,
                    'recurring_rule_id' => $rule->id,
                    'occurrence_on' => $key,
                    'flexibility' => $rule->flexibility,
                    'priority' => $rule->priority,
                    'lock_version' => 1,
                ]);

                $existingSet[$key] = true;
                $created++;
                if ($latest === null || $occurrence->gt($latest)) {
                    $latest = $occurrence;
                }
            }

            if ($latest !== null) {
                $rule->generated_through = $latest->toDateString();
                $rule->save();
            }

            return $created;
        });
    }

    /**
     * @return list<CarbonImmutable>
     */
    public function occurrenceDates(
        MoneyRecurringRule $rule,
        CarbonImmutable $from,
        CarbonImmutable $until,
        string $timezone,
    ): array {
        $interval = max(1, (int) $rule->interval_count);
        $startOn = CarbonImmutable::parse((string) $rule->start_on->toDateString(), $timezone)->startOfDay();

        return match ($rule->frequency) {
            MoneyRecurringFrequency::Weekly => $this->weeklyOccurrences($rule, $startOn, $from, $until, $interval),
            MoneyRecurringFrequency::Yearly => $this->yearlyOccurrences($rule, $startOn, $from, $until, $interval, $timezone),
            default => $this->monthlyOccurrences($rule, $startOn, $from, $until, $interval, $timezone),
        };
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function weeklyOccurrences(
        MoneyRecurringRule $rule,
        CarbonImmutable $startOn,
        CarbonImmutable $from,
        CarbonImmutable $until,
        int $interval,
    ): array {
        $targetDow = $rule->day_of_week;
        if ($targetDow === null) {
            $targetDow = $startOn->dayOfWeek;
        }

        $cursor = $startOn;
        while ($cursor->dayOfWeek !== (int) $targetDow) {
            $cursor = $cursor->addDay();
        }

        $dates = [];
        while ($cursor->lte($until)) {
            if ($cursor->gte($from)) {
                $dates[] = $cursor;
            }
            $cursor = $cursor->addWeeks($interval);
        }

        return $dates;
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function monthlyOccurrences(
        MoneyRecurringRule $rule,
        CarbonImmutable $startOn,
        CarbonImmutable $from,
        CarbonImmutable $until,
        int $interval,
        string $timezone,
    ): array {
        $dayOfMonth = $rule->day_of_month ?? $startOn->day;
        $cursorMonth = $startOn->startOfMonth();
        $dates = [];

        while ($cursorMonth->lte($until->endOfMonth())) {
            $occurrence = $this->dateForDayOfMonth($cursorMonth, (int) $dayOfMonth, $timezone);
            if ($occurrence->gte($startOn) && $occurrence->gte($from) && $occurrence->lte($until)) {
                $dates[] = $occurrence;
            }
            $cursorMonth = $cursorMonth->addMonthsNoOverflow($interval)->startOfMonth();
        }

        return $dates;
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function yearlyOccurrences(
        MoneyRecurringRule $rule,
        CarbonImmutable $startOn,
        CarbonImmutable $from,
        CarbonImmutable $until,
        int $interval,
        string $timezone,
    ): array {
        $month = $rule->month_of_year ?? $startOn->month;
        $dayOfMonth = $rule->day_of_month ?? $startOn->day;
        $year = $startOn->year;
        $dates = [];

        while (true) {
            $monthStart = CarbonImmutable::create($year, (int) $month, 1, 0, 0, 0, $timezone);
            if ($monthStart === null) {
                break;
            }
            $occurrence = $this->dateForDayOfMonth($monthStart->startOfDay(), (int) $dayOfMonth, $timezone);
            if ($occurrence->gt($until)) {
                break;
            }
            if ($occurrence->gte($startOn) && $occurrence->gte($from)) {
                $dates[] = $occurrence;
            }
            $year += $interval;
        }

        return $dates;
    }

    /**
     * Month-end rounding for day_of_month 29–31 when the month is shorter.
     */
    private function dateForDayOfMonth(CarbonImmutable $monthStart, int $dayOfMonth, string $timezone): CarbonImmutable
    {
        $lastDay = (int) $monthStart->endOfMonth()->day;
        $day = min(max(1, $dayOfMonth), $lastDay);

        return CarbonImmutable::create(
            (int) $monthStart->year,
            (int) $monthStart->month,
            $day,
            0,
            0,
            0,
            $timezone,
        )->startOfDay();
    }
}
