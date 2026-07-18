<?php

namespace App\Domain\Yoyu\Money\Services;

use App\Domain\Yoyu\Money\Enums\MoneyCashflowKind;
use App\Domain\Yoyu\Money\Enums\MoneyCashflowStatus;
use App\Domain\Yoyu\Money\Enums\MoneyCertainty;
use App\Domain\Yoyu\Money\Enums\MoneyCostBehavior;
use App\Domain\Yoyu\Money\Enums\MoneyDirection;
use App\Domain\Yoyu\Money\Enums\MoneyFlexibility;
use App\Domain\Yoyu\Money\Enums\MoneyIncomeAmountBasis;
use App\Domain\Yoyu\Money\Enums\MoneyPaymentMethod;
use App\Domain\Yoyu\Money\Enums\MoneyPriority;
use App\Domain\Yoyu\Money\Enums\MoneyTransactionKind;
use App\Domain\Yoyu\Money\Enums\MoneyTransactionSource;
use App\Domain\Yoyu\Money\Enums\MoneyTransactionStatus;
use App\Domain\Yoyu\Money\Models\MoneyAccount;
use App\Domain\Yoyu\Money\Models\MoneyCashflow;
use App\Domain\Yoyu\Money\Models\MoneyReconciliation;
use App\Domain\Yoyu\Money\Models\MoneyTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class MoneyCashflowService
{
    public function __construct(
        private readonly MoneyAuditService $auditService,
        private readonly MoneyReconciliationService $reconciliationService,
        private readonly MoneyAccountService $accountService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): MoneyCashflow
    {
        $direction = $this->enumOrValue(MoneyDirection::class, $data['direction'] ?? null);
        $kind = $this->enumOrValue(MoneyCashflowKind::class, $data['kind'] ?? null);
        $status = $this->enumOrValue(
            MoneyCashflowStatus::class,
            $data['status'] ?? MoneyCashflowStatus::Planned->value,
        );
        $certainty = $this->enumOrValue(
            MoneyCertainty::class,
            $data['certainty'] ?? MoneyCertainty::Confirmed->value,
        );
        $flexibility = $this->enumOrValue(
            MoneyFlexibility::class,
            $data['flexibility'] ?? MoneyFlexibility::Adjustable->value,
        );
        $priority = $this->enumOrValue(
            MoneyPriority::class,
            $data['priority'] ?? MoneyPriority::Normal->value,
        );

        /** @var MoneyCashflow $cashflow */
        $cashflow = MoneyCashflow::query()->withoutUserScope()->create([
            'user_id' => $user->id,
            'direction' => $direction,
            'kind' => $kind,
            'name' => (string) $data['name'],
            'amount_minor' => (int) $data['amount_minor'],
            'currency_code' => $data['currency_code'] ?? 'JPY',
            'due_on' => $data['due_on'],
            'original_due_on' => $data['original_due_on'] ?? $data['due_on'],
            'status' => $status,
            'certainty' => $certainty,
            'category_id' => $data['category_id'] ?? null,
            'counterparty_id' => $data['counterparty_id'] ?? null,
            'settlement_account_id' => $data['settlement_account_id'] ?? null,
            'credit_card_id' => $data['credit_card_id'] ?? null,
            'loan_id' => $data['loan_id'] ?? null,
            'payment_method' => isset($data['payment_method'])
                ? $this->enumOrValue(MoneyPaymentMethod::class, $data['payment_method'])
                : null,
            'income_amount_basis' => isset($data['income_amount_basis'])
                ? $this->enumOrValue(MoneyIncomeAmountBasis::class, $data['income_amount_basis'])
                : null,
            'cost_behavior' => isset($data['cost_behavior'])
                ? $this->enumOrValue(MoneyCostBehavior::class, $data['cost_behavior'])
                : null,
            'recurring_rule_id' => $data['recurring_rule_id'] ?? null,
            'occurrence_on' => $data['occurrence_on'] ?? null,
            'supersedes_id' => $data['supersedes_id'] ?? null,
            'flexibility' => $flexibility,
            'priority' => $priority,
            'memo' => $data['memo'] ?? null,
            'lock_version' => 1,
        ]);

        $this->auditService->record(
            (int) $user->id,
            'money_cashflow.created',
            MoneyCashflow::class,
            (string) $cashflow->id,
            null,
            [
                'id' => $cashflow->id,
                'name' => $cashflow->name,
                'direction' => $cashflow->direction->value,
                'kind' => $cashflow->kind->value,
                'amount_minor' => $cashflow->amount_minor,
                'due_on' => (string) $cashflow->due_on?->toDateString(),
                'status' => $cashflow->status->value,
                'certainty' => $cashflow->certainty->value,
            ],
        );

        return $cashflow;
    }

    public function defer(
        User $user,
        MoneyCashflow $cashflow,
        string $newDueOn,
        int $lockVersion,
    ): MoneyCashflow {
        $this->assertOwned($user, $cashflow);

        return DB::transaction(function () use ($user, $cashflow, $newDueOn, $lockVersion): MoneyCashflow {
            /** @var MoneyCashflow|null $locked */
            $locked = MoneyCashflow::query()
                ->withoutUserScope()
                ->whereKey($cashflow->id)
                ->lockForUpdate()
                ->first();

            abort_unless($locked !== null, 404);

            if ((int) $locked->lock_version !== $lockVersion) {
                abort(409, 'Cashflow lock version mismatch.');
            }

            if (in_array($locked->status, [
                MoneyCashflowStatus::Settled,
                MoneyCashflowStatus::Canceled,
                MoneyCashflowStatus::Deferred,
            ], true)) {
                throw new InvalidArgumentException('Cashflow cannot be deferred in its current status.');
            }

            $before = [
                'id' => $locked->id,
                'status' => $locked->status->value,
                'due_on' => (string) $locked->due_on?->toDateString(),
                'lock_version' => $locked->lock_version,
            ];

            $originalDueOn = $locked->original_due_on ?? $locked->due_on;

            $locked->status = MoneyCashflowStatus::Deferred;
            $locked->lock_version = (int) $locked->lock_version + 1;
            $locked->save();

            $replacement = $this->create($user, [
                'direction' => $locked->direction,
                'kind' => $locked->kind,
                'name' => $locked->name,
                'amount_minor' => $this->reconciliationService->remainingAmountMinor($locked),
                'currency_code' => $locked->currency_code,
                'due_on' => $newDueOn,
                'original_due_on' => $originalDueOn,
                'status' => MoneyCashflowStatus::Planned,
                'certainty' => $locked->certainty,
                'category_id' => $locked->category_id,
                'counterparty_id' => $locked->counterparty_id,
                'settlement_account_id' => $locked->settlement_account_id,
                'credit_card_id' => $locked->credit_card_id,
                'loan_id' => $locked->loan_id,
                'payment_method' => $locked->payment_method,
                'income_amount_basis' => $locked->income_amount_basis,
                'cost_behavior' => $locked->cost_behavior,
                'flexibility' => $locked->flexibility,
                'priority' => $locked->priority,
                'memo' => $locked->memo,
                'supersedes_id' => $locked->id,
            ]);

            $this->auditService->record(
                (int) $user->id,
                'money_cashflow.deferred',
                MoneyCashflow::class,
                (string) $locked->id,
                $before,
                [
                    'id' => $locked->id,
                    'status' => $locked->status->value,
                    'supersedes_id' => $replacement->id,
                    'due_on' => $newDueOn,
                ],
            );

            return $replacement;
        });
    }

    public function cancel(User $user, MoneyCashflow $cashflow, int $lockVersion): MoneyCashflow
    {
        $this->assertOwned($user, $cashflow);

        return DB::transaction(function () use ($user, $cashflow, $lockVersion): MoneyCashflow {
            /** @var MoneyCashflow|null $locked */
            $locked = MoneyCashflow::query()
                ->withoutUserScope()
                ->whereKey($cashflow->id)
                ->lockForUpdate()
                ->first();

            abort_unless($locked !== null, 404);

            if ((int) $locked->lock_version !== $lockVersion) {
                abort(409, 'Cashflow lock version mismatch.');
            }

            if ($locked->status === MoneyCashflowStatus::Settled) {
                throw new InvalidArgumentException('Settled cashflow cannot be canceled.');
            }

            $before = [
                'id' => $locked->id,
                'status' => $locked->status->value,
                'lock_version' => $locked->lock_version,
            ];

            $locked->status = MoneyCashflowStatus::Canceled;
            $locked->lock_version = (int) $locked->lock_version + 1;
            $locked->save();

            $this->auditService->record(
                (int) $user->id,
                'money_cashflow.canceled',
                MoneyCashflow::class,
                (string) $locked->id,
                $before,
                [
                    'id' => $locked->id,
                    'status' => $locked->status->value,
                    'lock_version' => $locked->lock_version,
                ],
            );

            return $locked->refresh();
        });
    }

    /**
     * @param  array{
     *     amount_minor: int,
     *     occurred_on: string,
     *     account_id?: string|null,
     *     create_transaction?: bool,
     *     update_balance?: bool,
     *     note?: string|null
     * }  $options
     * @return array{
     *     cashflow: MoneyCashflow,
     *     reconciliation: MoneyReconciliation,
     *     transaction: MoneyTransaction|null,
     *     remaining_minor: int
     * }
     */
    public function settle(User $user, MoneyCashflow $cashflow, array $options): array
    {
        $this->assertOwned($user, $cashflow);

        return DB::transaction(function () use ($user, $cashflow, $options): array {
            /** @var MoneyCashflow|null $locked */
            $locked = MoneyCashflow::query()
                ->withoutUserScope()
                ->whereKey($cashflow->id)
                ->lockForUpdate()
                ->first();

            abort_unless($locked !== null, 404);

            if (in_array($locked->status, [
                MoneyCashflowStatus::Settled,
                MoneyCashflowStatus::Canceled,
                MoneyCashflowStatus::Deferred,
            ], true)) {
                throw new InvalidArgumentException('Cashflow cannot be settled in its current status.');
            }

            $amountMinor = (int) $options['amount_minor'];
            if ($amountMinor <= 0) {
                throw new InvalidArgumentException('Settlement amount must be positive.');
            }

            $remaining = $this->reconciliationService->remainingAmountMinor($locked);
            if ($amountMinor > $remaining) {
                throw new InvalidArgumentException('Settlement amount exceeds remaining cashflow amount.');
            }

            $createTransaction = (bool) ($options['create_transaction'] ?? true);
            $updateBalance = (bool) ($options['update_balance'] ?? false);
            $occurredOn = (string) $options['occurred_on'];
            $accountId = $options['account_id'] ?? $locked->settlement_account_id;
            $note = $options['note'] ?? null;

            // Reconciliation FK requires a transaction row; system source when not requested.
            $transaction = MoneyTransaction::query()->withoutUserScope()->create([
                'user_id' => $user->id,
                'account_id' => $accountId,
                'direction' => $locked->direction,
                'kind' => $this->transactionKindFor($locked),
                'amount_minor' => $amountMinor,
                'currency_code' => $locked->currency_code,
                'occurred_on' => $occurredOn,
                'posted_on' => $occurredOn,
                'description_raw' => $locked->name,
                'description_normalized' => $locked->name,
                'category_id' => $locked->category_id,
                'counterparty_id' => $locked->counterparty_id,
                'credit_card_id' => $locked->credit_card_id,
                'loan_id' => $locked->loan_id,
                'payment_method' => $locked->payment_method,
                'cost_behavior' => $locked->cost_behavior,
                'status' => MoneyTransactionStatus::Posted,
                'source' => $createTransaction
                    ? MoneyTransactionSource::Manual
                    : MoneyTransactionSource::System,
                'memo' => $note,
            ]);

            /** @var MoneyReconciliation $reconciliation */
            $reconciliation = MoneyReconciliation::query()->withoutUserScope()->create([
                'user_id' => $user->id,
                'cashflow_id' => $locked->id,
                'transaction_id' => $transaction->id,
                'amount_minor' => $amountMinor,
                'reconciled_at' => Date::now(),
                'source' => $createTransaction
                    ? MoneyTransactionSource::Manual
                    : MoneyTransactionSource::System,
                'note' => $note,
            ]);

            $newRemaining = $this->reconciliationService->remainingAmountMinor($locked->refresh());
            $locked->status = $newRemaining === 0
                ? MoneyCashflowStatus::Settled
                : MoneyCashflowStatus::PartiallySettled;
            if ($locked->status === MoneyCashflowStatus::Settled) {
                $locked->settled_at = Date::now();
            }
            $locked->lock_version = (int) $locked->lock_version + 1;
            $locked->save();

            if ($updateBalance && $accountId !== null) {
                /** @var MoneyAccount|null $account */
                $account = MoneyAccount::query()
                    ->withoutUserScope()
                    ->whereKey($accountId)
                    ->where('user_id', $user->id)
                    ->first();

                abort_unless($account !== null, 404);

                $delta = $locked->direction === MoneyDirection::Inflow
                    ? $amountMinor
                    : -$amountMinor;
                $newBalance = (int) $account->current_balance_minor + $delta;
                $newAvailable = $account->available_balance_minor !== null
                    ? (int) $account->available_balance_minor + $delta
                    : null;

                $this->accountService->updateBalance(
                    $user,
                    $account,
                    $newBalance,
                    $newAvailable,
                    $note ?? 'cashflow_settlement',
                    (int) $account->lock_version,
                );
            }

            $this->auditService->record(
                (int) $user->id,
                'money_cashflow.settled',
                MoneyCashflow::class,
                (string) $locked->id,
                [
                    'status' => $cashflow->status->value,
                    'amount_minor' => $cashflow->amount_minor,
                ],
                [
                    'status' => $locked->status->value,
                    'amount_minor' => $amountMinor,
                    'remaining_minor' => $newRemaining,
                    'transaction_id' => $transaction?->id,
                ],
            );

            return [
                'cashflow' => $locked->refresh(),
                'reconciliation' => $reconciliation,
                'transaction' => $createTransaction ? $transaction : null,
                'remaining_minor' => $newRemaining,
            ];
        });
    }

    private function transactionKindFor(MoneyCashflow $cashflow): MoneyTransactionKind
    {
        return match ($cashflow->kind) {
            MoneyCashflowKind::Income => MoneyTransactionKind::Income,
            MoneyCashflowKind::CardStatement => MoneyTransactionKind::CardPayment,
            MoneyCashflowKind::LoanPayment => MoneyTransactionKind::LoanPayment,
            MoneyCashflowKind::Transfer => MoneyTransactionKind::Transfer,
            default => MoneyTransactionKind::Purchase,
        };
    }

    /**
     * @template T of \BackedEnum
     *
     * @param  class-string<T>  $enumClass
     * @return T
     */
    private function enumOrValue(string $enumClass, mixed $value): \BackedEnum
    {
        if ($value instanceof $enumClass) {
            return $value;
        }

        if ($value === null) {
            throw new InvalidArgumentException("Missing value for {$enumClass}.");
        }

        return $enumClass::from((string) $value);
    }

    private function assertOwned(User $user, MoneyCashflow $cashflow): void
    {
        abort_unless((int) $cashflow->user_id === (int) $user->id, 404);
    }
}
