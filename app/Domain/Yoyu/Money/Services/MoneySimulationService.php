<?php

namespace App\Domain\Yoyu\Money\Services;

use App\Domain\Yoyu\Money\Enums\MoneyCashflowStatus;
use App\Domain\Yoyu\Money\Enums\MoneyDecisionStatus;
use App\Domain\Yoyu\Money\Enums\MoneyDirection;
use App\Domain\Yoyu\Money\Enums\MoneySimulationActionType;
use App\Domain\Yoyu\Money\Enums\MoneySimulationStatus;
use App\Domain\Yoyu\Money\Models\MoneyAccount;
use App\Domain\Yoyu\Money\Models\MoneyCashflow;
use App\Domain\Yoyu\Money\Models\MoneyRecurringRule;
use App\Domain\Yoyu\Money\Models\MoneySetting;
use App\Domain\Yoyu\Money\Models\MoneySimulation;
use App\Domain\Yoyu\Money\Models\MoneySimulationAction;
use App\Domain\Yoyu\Support\UserTimezoneResolver;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class MoneySimulationService
{
    public function __construct(
        private readonly MoneyAuditService $auditService,
        private readonly MoneySetupService $setupService,
        private readonly MoneyProjectionQuery $projectionQuery,
        private readonly MarginCalculator $marginCalculator,
        private readonly MoneyReconciliationService $reconciliationService,
        private readonly MoneyCashflowService $cashflowService,
        private readonly MoneyDecisionService $decisionService,
        private readonly UserTimezoneResolver $timezoneResolver,
        private readonly RecurringCashflowGenerator $recurringCashflowGenerator,
    ) {}

    /**
     * @param  array{name?: string, horizon_months?: int, memo?: string|null}  $data
     */
    public function create(User $user, array $data = []): MoneySimulation
    {
        $settings = $this->setupService->ensureForUser($user);
        $this->recurringCashflowGenerator->generateForUser($user);

        $timezone = $this->timezoneResolver->for($user);
        $baseDate = CarbonImmutable::now($timezone)->toDateString();
        $fingerprint = $this->computeFingerprint($user, $settings);
        $baselineProjection = $this->projectionQuery->forUser($user);

        /** @var MoneySimulation $simulation */
        $simulation = MoneySimulation::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'name' => (string) ($data['name'] ?? 'シミュレーション'),
            'status' => MoneySimulationStatus::Draft,
            'base_date' => $baseDate,
            'horizon_months' => (int) ($data['horizon_months'] ?? $settings->calculation_horizon_months ?? 3),
            'formula_version' => (string) $settings->formula_version,
            'currency_code' => (string) $settings->currency_code,
            'assumptions_payload' => [
                'fingerprint' => $fingerprint,
            ],
            'baseline_payload' => [
                'fingerprint' => $fingerprint,
                'projection' => $baselineProjection,
            ],
            'memo' => $data['memo'] ?? null,
        ]);

        $this->auditService->record(
            (int) $user->id,
            'money_simulation.created',
            MoneySimulation::class,
            (string) $simulation->id,
            null,
            [
                'id' => $simulation->id,
                'name' => $simulation->name,
                'status' => $simulation->status->value,
            ],
        );

        return $simulation;
    }

    /**
     * @param  array{
     *     action_type: string|MoneySimulationActionType,
     *     sort_order?: int,
     *     target_type?: string|null,
     *     target_id?: string|null,
     *     params_payload?: array<string, mixed>|null
     * }  $data
     */
    public function addAction(User $user, MoneySimulation $simulation, array $data): MoneySimulationAction
    {
        $this->assertOwned($user, $simulation);

        if (! in_array($simulation->status, [
            MoneySimulationStatus::Draft,
            MoneySimulationStatus::Calculated,
            MoneySimulationStatus::Stale,
        ], true)) {
            throw new InvalidArgumentException('Cannot add actions to this simulation.');
        }

        $actionType = $data['action_type'] instanceof MoneySimulationActionType
            ? $data['action_type']
            : MoneySimulationActionType::from((string) $data['action_type']);

        if ($actionType === MoneySimulationActionType::ChangeCardPayment) {
            $params = $data['params_payload'] ?? [];
            foreach ([
                'this_month_reduction_minor',
                'future_monthly_minor',
                'months',
                'fee_estimate_minor',
            ] as $required) {
                if (! array_key_exists($required, $params)) {
                    throw new InvalidArgumentException("change_card_payment requires {$required}.");
                }
            }
        }

        $sortOrder = (int) ($data['sort_order'] ?? (
            (int) MoneySimulationAction::query()
                ->withoutUserScope()
                ->where('simulation_id', $simulation->id)
                ->max('sort_order') + 1
        ));

        /** @var MoneySimulationAction $action */
        $action = MoneySimulationAction::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'simulation_id' => $simulation->id,
            'action_type' => $actionType,
            'sort_order' => $sortOrder,
            'target_type' => $data['target_type'] ?? null,
            'target_id' => $data['target_id'] ?? null,
            'params_payload' => $data['params_payload'] ?? null,
        ]);

        if ($simulation->status === MoneySimulationStatus::Calculated) {
            $simulation->status = MoneySimulationStatus::Draft;
            $simulation->result_payload = null;
            $simulation->save();
        }

        return $action;
    }

    public function calculate(User $user, MoneySimulation $simulation): MoneySimulation
    {
        $this->assertOwned($user, $simulation);
        $this->markStaleIfFingerprintChanged($user, $simulation);

        if ($simulation->status === MoneySimulationStatus::Stale) {
            abort(409, 'Simulation baseline is stale.');
        }

        $settings = $this->setupService->ensureForUser($user);
        $timezone = $this->timezoneResolver->for($user);
        $now = CarbonImmutable::now($timezone);
        $asOf = $now->toDateString();

        $virtualCashflows = $this->loadVirtualCashflows($user);
        $actions = MoneySimulationAction::query()
            ->withoutUserScope()
            ->where('simulation_id', $simulation->id)
            ->orderBy('sort_order')
            ->get();

        $actionEffects = [];
        foreach ($actions as $action) {
            $effect = $this->applyActionVirtually($virtualCashflows, $action);
            $action->effect_payload = $effect;
            $action->save();
            $actionEffects[] = [
                'action_id' => $action->id,
                'action_type' => $action->action_type->value,
                'effect' => $effect,
            ];
        }

        $fundsMinor = (int) MoneyAccount::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->get()
            ->sum(fn (MoneyAccount $account): int => (int) ($account->available_balance_minor ?? $account->current_balance_minor));

        $horizons = [
            'this_month' => $now->endOfMonth()->toDateString(),
            'next_month' => $now->addMonthNoOverflow()->endOfMonth()->toDateString(),
            'three_months' => $now->addMonthsNoOverflow(3)->endOfMonth()->toDateString(),
        ];

        $margins = [];
        foreach ($horizons as $label => $horizonEnd) {
            $margins[$label] = $this->marginCalculator->calculate(
                fundsMinor: $fundsMinor,
                cashflows: $virtualCashflows,
                uncertainReserveBps: (int) $settings->uncertain_outflow_reserve_bps,
                minimumLivingBudgetMinor: $settings->minimum_living_budget_minor !== null
                    ? (int) $settings->minimum_living_budget_minor
                    : null,
                safetyBufferMinor: $settings->safety_buffer_minor !== null
                    ? (int) $settings->safety_buffer_minor
                    : null,
                essentialConsumedMinor: 0,
                essentialScheduledMinor: 0,
                asOf: $asOf,
                horizonEnd: $horizonEnd,
                formulaVersion: (string) $settings->formula_version,
                includeExpectedIncome: (bool) $settings->include_expected_income,
            )->toArray();
        }

        $cardPaymentParams = null;
        foreach ($actions as $action) {
            if ($action->action_type === MoneySimulationActionType::ChangeCardPayment) {
                $cardPaymentParams = $action->params_payload;
            }
        }

        $result = [
            'fingerprint' => $this->fingerprintFromSimulation($simulation),
            'margins' => $margins,
            'actions' => $actionEffects,
            'recommendation' => null,
            'disclaimer' => 'This simulation never recommends actions. Values are informational only.',
        ];

        if ($cardPaymentParams !== null) {
            $result['change_card_payment'] = [
                'this_month_reduction_minor' => (string) ($cardPaymentParams['this_month_reduction_minor'] ?? '0'),
                'future_monthly_minor' => (string) ($cardPaymentParams['future_monthly_minor'] ?? '0'),
                'months' => (int) ($cardPaymentParams['months'] ?? 0),
                'fee_estimate_minor' => (string) ($cardPaymentParams['fee_estimate_minor'] ?? '0'),
            ];
        }

        $simulation->result_payload = $result;
        $simulation->status = MoneySimulationStatus::Calculated;
        $simulation->save();

        $this->auditService->record(
            (int) $user->id,
            'money_simulation.calculated',
            MoneySimulation::class,
            (string) $simulation->id,
            null,
            [
                'id' => $simulation->id,
                'status' => $simulation->status->value,
            ],
        );

        return $simulation->refresh();
    }

    public function markStaleIfFingerprintChanged(User $user, MoneySimulation $simulation): MoneySimulation
    {
        $this->assertOwned($user, $simulation);

        if (in_array($simulation->status, [
            MoneySimulationStatus::Applied,
            MoneySimulationStatus::Discarded,
        ], true)) {
            return $simulation;
        }

        $settings = $this->setupService->ensureForUser($user);
        $current = $this->computeFingerprint($user, $settings);
        $baseline = $this->fingerprintFromSimulation($simulation);

        if ($baseline !== null && ! hash_equals($baseline, $current)) {
            $simulation->status = MoneySimulationStatus::Stale;
            $assumptions = $simulation->assumptions_payload ?? [];
            $assumptions['stale_fingerprint'] = $current;
            $simulation->assumptions_payload = $assumptions;
            $simulation->save();
        }

        return $simulation->refresh();
    }

    public function apply(User $user, MoneySimulation $simulation): MoneySimulation
    {
        $this->assertOwned($user, $simulation);

        if ($simulation->status !== MoneySimulationStatus::Calculated) {
            throw new InvalidArgumentException('Simulation must be calculated before apply.');
        }

        $this->markStaleIfFingerprintChanged($user, $simulation);
        if ($simulation->status === MoneySimulationStatus::Stale) {
            abort(409, 'Simulation baseline is stale.');
        }

        return DB::transaction(function () use ($user, $simulation): MoneySimulation {
            $actions = MoneySimulationAction::query()
                ->withoutUserScope()
                ->where('simulation_id', $simulation->id)
                ->orderBy('sort_order')
                ->lockForUpdate()
                ->get();

            $appliedEffects = [];
            $requiresExternal = false;

            foreach ($actions as $action) {
                if ($action->action_type === MoneySimulationActionType::DeferCashflow) {
                    $params = $action->params_payload ?? [];
                    $newDueOn = (string) ($params['new_due_on'] ?? '');
                    if ($action->target_id === null || $newDueOn === '') {
                        throw new InvalidArgumentException('defer_cashflow requires target_id and new_due_on.');
                    }

                    /** @var MoneyCashflow|null $cashflow */
                    $cashflow = MoneyCashflow::query()
                        ->withoutUserScope()
                        ->whereKey($action->target_id)
                        ->where('user_id', $user->id)
                        ->first();

                    abort_unless($cashflow !== null, 404);

                    $replacement = $this->cashflowService->defer(
                        $user,
                        $cashflow,
                        $newDueOn,
                        (int) $cashflow->lock_version,
                    );
                    $appliedEffects[] = [
                        'action_type' => $action->action_type->value,
                        'cashflow_id' => $replacement->id,
                    ];

                    continue;
                }

                if ($action->action_type === MoneySimulationActionType::PauseRecurring) {
                    if ($action->target_id === null) {
                        throw new InvalidArgumentException('pause_recurring requires target_id.');
                    }

                    /** @var MoneyRecurringRule|null $rule */
                    $rule = MoneyRecurringRule::query()
                        ->withoutUserScope()
                        ->whereKey($action->target_id)
                        ->where('user_id', $user->id)
                        ->first();

                    abort_unless($rule !== null, 404);

                    $rule->is_active = false;
                    $rule->save();
                    $appliedEffects[] = [
                        'action_type' => $action->action_type->value,
                        'recurring_rule_id' => $rule->id,
                    ];

                    continue;
                }

                // External / non-auto-applicable actions (card payment changes, prepay, etc.)
                $requiresExternal = true;
                $appliedEffects[] = [
                    'action_type' => $action->action_type->value,
                    'external' => true,
                    'params' => $action->params_payload,
                ];
            }

            $decisionStatus = $requiresExternal
                ? MoneyDecisionStatus::ActionRequired
                : MoneyDecisionStatus::Executed;

            $this->decisionService->createManual($user, [
                'title' => $simulation->name,
                'decided_on' => Date::now()->toDateString(),
                'status' => $decisionStatus,
                'simulation_id' => $simulation->id,
                'before_payload' => $simulation->baseline_payload,
                'expected_effect_payload' => $simulation->result_payload,
                'memo' => $simulation->memo,
            ]);

            $simulation->status = MoneySimulationStatus::Applied;
            $result = $simulation->result_payload ?? [];
            $result['applied_effects'] = $appliedEffects;
            $simulation->result_payload = $result;
            $simulation->save();

            $this->auditService->record(
                (int) $user->id,
                'money_simulation.applied',
                MoneySimulation::class,
                (string) $simulation->id,
                null,
                [
                    'id' => $simulation->id,
                    'status' => $simulation->status->value,
                ],
            );

            return $simulation->refresh();
        });
    }

    public function discard(User $user, MoneySimulation $simulation): MoneySimulation
    {
        $this->assertOwned($user, $simulation);

        if ($simulation->status === MoneySimulationStatus::Applied) {
            throw new InvalidArgumentException('Applied simulation cannot be discarded.');
        }

        $simulation->status = MoneySimulationStatus::Discarded;
        $simulation->save();

        $this->auditService->record(
            (int) $user->id,
            'money_simulation.discarded',
            MoneySimulation::class,
            (string) $simulation->id,
            null,
            [
                'id' => $simulation->id,
                'status' => $simulation->status->value,
            ],
        );

        return $simulation->refresh();
    }

    public function computeFingerprint(User $user, ?MoneySetting $settings = null): string
    {
        $settings ??= $this->setupService->ensureForUser($user);

        $settingsPart = implode('|', [
            (string) $settings->minimum_living_budget_minor,
            (string) $settings->safety_buffer_minor,
            (string) $settings->uncertain_outflow_reserve_bps,
            $settings->include_expected_income ? '1' : '0',
            (string) $settings->calculation_horizon_months,
            (string) $settings->formula_version,
            (string) $settings->updated_at?->toIso8601String(),
        ]);

        $accountsPart = MoneyAccount::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get(['id', 'current_balance_minor', 'available_balance_minor', 'updated_at'])
            ->map(fn (MoneyAccount $a): string => implode(':', [
                $a->id,
                (string) $a->current_balance_minor,
                (string) $a->available_balance_minor,
                (string) $a->updated_at?->toIso8601String(),
            ]))
            ->implode(';');

        $cashflowsPart = MoneyCashflow::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get(['id', 'updated_at'])
            ->map(fn (MoneyCashflow $c): string => $c->id.':'.(string) $c->updated_at?->toIso8601String())
            ->implode(';');

        return hash('sha256', $settingsPart.'||'.$accountsPart.'||'.$cashflowsPart);
    }

    /**
     * @return list<array{
     *     id?: string,
     *     direction: string,
     *     amount_minor: int,
     *     certainty: string,
     *     status: string,
     *     due_on: string,
     *     kind: string,
     *     remaining_minor: int,
     *     category_is_essential: bool,
     *     virtual?: bool
     * }>
     */
    private function loadVirtualCashflows(User $user): array
    {
        $models = MoneyCashflow::query()
            ->withoutUserScope()
            ->with('category')
            ->where('user_id', $user->id)
            ->whereNotIn('status', [
                MoneyCashflowStatus::Settled->value,
                MoneyCashflowStatus::Canceled->value,
                MoneyCashflowStatus::Deferred->value,
            ])
            ->orderBy('due_on')
            ->get();

        $rows = [];
        foreach ($models as $cashflow) {
            $rows[] = [
                'id' => $cashflow->id,
                'direction' => $cashflow->direction->value,
                'amount_minor' => (int) $cashflow->amount_minor,
                'certainty' => $cashflow->certainty->value,
                'status' => $cashflow->status->value,
                'due_on' => (string) $cashflow->due_on?->toDateString(),
                'kind' => $cashflow->kind->value,
                'remaining_minor' => $this->reconciliationService->remainingAmountMinor($cashflow),
                'category_is_essential' => (bool) ($cashflow->category?->is_essential ?? false),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $virtualCashflows
     * @return array<string, mixed>
     */
    private function applyActionVirtually(array &$virtualCashflows, MoneySimulationAction $action): array
    {
        return match ($action->action_type) {
            MoneySimulationActionType::DeferCashflow => $this->virtuallyDefer($virtualCashflows, $action),
            MoneySimulationActionType::PauseRecurring => $this->virtuallyPauseRecurring($virtualCashflows, $action),
            MoneySimulationActionType::ChangeCardPayment => $this->virtuallyChangeCardPayment($virtualCashflows, $action),
            MoneySimulationActionType::CapCategory => $this->virtuallyCapCategory($virtualCashflows, $action),
            MoneySimulationActionType::AddPurchase => $this->virtuallyAddPurchase($virtualCashflows, $action),
            MoneySimulationActionType::PrepayLoan => $this->virtuallyPrepayLoan($virtualCashflows, $action),
            MoneySimulationActionType::AdjustIncome => $this->virtuallyAdjustIncome($virtualCashflows, $action),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $virtualCashflows
     * @return array<string, mixed>
     */
    private function virtuallyDefer(array &$virtualCashflows, MoneySimulationAction $action): array
    {
        $params = $action->params_payload ?? [];
        $newDueOn = (string) ($params['new_due_on'] ?? '');
        $targetId = $action->target_id;

        foreach ($virtualCashflows as &$cf) {
            if (($cf['id'] ?? null) === $targetId) {
                $before = $cf['due_on'];
                $cf['due_on'] = $newDueOn;

                return ['before_due_on' => $before, 'after_due_on' => $newDueOn];
            }
        }

        return ['skipped' => true, 'reason' => 'target_not_found'];
    }

    /**
     * @param  list<array<string, mixed>>  $virtualCashflows
     * @return array<string, mixed>
     */
    private function virtuallyPauseRecurring(array &$virtualCashflows, MoneySimulationAction $action): array
    {
        $removed = 0;
        $targetId = $action->target_id;
        $ids = $action->params_payload['cashflow_ids'] ?? [];

        if (is_array($ids) && $ids !== []) {
            $idSet = array_fill_keys(array_map('strval', $ids), true);
            $virtualCashflows = array_values(array_filter(
                $virtualCashflows,
                function (array $cf) use ($idSet, &$removed): bool {
                    if (isset($cf['id'], $idSet[(string) $cf['id']])) {
                        $removed++;

                        return false;
                    }

                    return true;
                },
            ));
        }

        return [
            'target_id' => $targetId,
            'removed_cashflows' => $removed,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $virtualCashflows
     * @return array<string, mixed>
     */
    private function virtuallyChangeCardPayment(array &$virtualCashflows, MoneySimulationAction $action): array
    {
        $params = $action->params_payload ?? [];
        $reduction = (int) ($params['this_month_reduction_minor'] ?? 0);
        $futureMonthly = (int) ($params['future_monthly_minor'] ?? 0);
        $months = (int) ($params['months'] ?? 0);
        $fee = (int) ($params['fee_estimate_minor'] ?? 0);

        // Show effect on virtual outflows: reduce nearest card_statement by reduction.
        foreach ($virtualCashflows as &$cf) {
            if (($cf['kind'] ?? '') === 'card_statement' && $reduction > 0) {
                $cf['amount_minor'] = max(0, (int) $cf['amount_minor'] - $reduction);
                $cf['remaining_minor'] = max(0, (int) ($cf['remaining_minor'] ?? $cf['amount_minor']) - $reduction);
                break;
            }
        }
        unset($cf);

        if ($fee > 0) {
            $virtualCashflows[] = [
                'direction' => MoneyDirection::Outflow->value,
                'amount_minor' => $fee,
                'certainty' => 'expected',
                'status' => MoneyCashflowStatus::Planned->value,
                'due_on' => CarbonImmutable::now()->endOfMonth()->toDateString(),
                'kind' => 'expense',
                'remaining_minor' => $fee,
                'category_is_essential' => false,
                'virtual' => true,
            ];
        }

        // Never recommend — only surface the user-provided params.
        return [
            'this_month_reduction_minor' => (string) $reduction,
            'future_monthly_minor' => (string) $futureMonthly,
            'months' => $months,
            'fee_estimate_minor' => (string) $fee,
            'recommendation' => null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $virtualCashflows
     * @return array<string, mixed>
     */
    private function virtuallyCapCategory(array &$virtualCashflows, MoneySimulationAction $action): array
    {
        $cap = (int) (($action->params_payload['cap_minor'] ?? 0));

        return ['cap_minor' => $cap, 'note' => 'cap_applied_informational'];
    }

    /**
     * @param  list<array<string, mixed>>  $virtualCashflows
     * @return array<string, mixed>
     */
    private function virtuallyAddPurchase(array &$virtualCashflows, MoneySimulationAction $action): array
    {
        $params = $action->params_payload ?? [];
        $amount = (int) ($params['amount_minor'] ?? 0);
        $dueOn = (string) ($params['due_on'] ?? CarbonImmutable::now()->toDateString());

        $virtualCashflows[] = [
            'direction' => MoneyDirection::Outflow->value,
            'amount_minor' => $amount,
            'certainty' => 'confirmed',
            'status' => MoneyCashflowStatus::Planned->value,
            'due_on' => $dueOn,
            'kind' => 'expense',
            'remaining_minor' => $amount,
            'category_is_essential' => false,
            'virtual' => true,
        ];

        return ['amount_minor' => $amount, 'due_on' => $dueOn];
    }

    /**
     * @param  list<array<string, mixed>>  $virtualCashflows
     * @return array<string, mixed>
     */
    private function virtuallyPrepayLoan(array &$virtualCashflows, MoneySimulationAction $action): array
    {
        $amount = (int) (($action->params_payload['amount_minor'] ?? 0));
        $dueOn = (string) (($action->params_payload['due_on'] ?? CarbonImmutable::now()->toDateString()));

        if ($amount > 0) {
            $virtualCashflows[] = [
                'direction' => MoneyDirection::Outflow->value,
                'amount_minor' => $amount,
                'certainty' => 'confirmed',
                'status' => MoneyCashflowStatus::Planned->value,
                'due_on' => $dueOn,
                'kind' => 'loan_payment',
                'remaining_minor' => $amount,
                'category_is_essential' => false,
                'virtual' => true,
            ];
        }

        return ['amount_minor' => $amount, 'due_on' => $dueOn, 'external' => true];
    }

    /**
     * @param  list<array<string, mixed>>  $virtualCashflows
     * @return array<string, mixed>
     */
    private function virtuallyAdjustIncome(array &$virtualCashflows, MoneySimulationAction $action): array
    {
        $params = $action->params_payload ?? [];
        $amount = (int) ($params['amount_minor'] ?? 0);
        $dueOn = (string) ($params['due_on'] ?? CarbonImmutable::now()->toDateString());

        $virtualCashflows[] = [
            'direction' => MoneyDirection::Inflow->value,
            'amount_minor' => $amount,
            'certainty' => (string) ($params['certainty'] ?? 'expected'),
            'status' => MoneyCashflowStatus::Planned->value,
            'due_on' => $dueOn,
            'kind' => 'income',
            'remaining_minor' => $amount,
            'category_is_essential' => false,
            'virtual' => true,
        ];

        return ['amount_minor' => $amount, 'due_on' => $dueOn];
    }

    private function fingerprintFromSimulation(MoneySimulation $simulation): ?string
    {
        $baseline = $simulation->baseline_payload ?? [];
        $assumptions = $simulation->assumptions_payload ?? [];

        if (isset($baseline['fingerprint']) && is_string($baseline['fingerprint'])) {
            return $baseline['fingerprint'];
        }

        if (isset($assumptions['fingerprint']) && is_string($assumptions['fingerprint'])) {
            return $assumptions['fingerprint'];
        }

        return null;
    }

    private function assertOwned(User $user, MoneySimulation $simulation): void
    {
        abort_unless((int) $simulation->user_id === (int) $user->id, 404);
    }
}
