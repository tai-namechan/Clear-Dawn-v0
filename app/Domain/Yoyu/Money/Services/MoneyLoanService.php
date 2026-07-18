<?php

namespace App\Domain\Yoyu\Money\Services;

use App\Domain\Yoyu\Money\Enums\MoneyCashflowKind;
use App\Domain\Yoyu\Money\Enums\MoneyCashflowStatus;
use App\Domain\Yoyu\Money\Enums\MoneyCertainty;
use App\Domain\Yoyu\Money\Enums\MoneyDirection;
use App\Domain\Yoyu\Money\Enums\MoneyFlexibility;
use App\Domain\Yoyu\Money\Enums\MoneyLoanStatus;
use App\Domain\Yoyu\Money\Enums\MoneyLoanType;
use App\Domain\Yoyu\Money\Enums\MoneyPriority;
use App\Domain\Yoyu\Money\Enums\MoneyTransactionKind;
use App\Domain\Yoyu\Money\Enums\MoneyTransactionSource;
use App\Domain\Yoyu\Money\Enums\MoneyTransactionStatus;
use App\Domain\Yoyu\Money\Models\MoneyCashflow;
use App\Domain\Yoyu\Money\Models\MoneyLoan;
use App\Domain\Yoyu\Money\Models\MoneyLoanPayment;
use App\Domain\Yoyu\Money\Models\MoneyTransaction;
use App\Domain\Yoyu\Money\Support\LoanInterestEstimator;
use App\Domain\Yoyu\Money\Support\MoneyAmount;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class MoneyLoanService
{
    public function __construct(
        private readonly MoneyAuditService $auditService,
        private readonly MoneyCashflowService $cashflowService,
        private readonly LoanInterestEstimator $interestEstimator,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): MoneyLoan
    {
        return DB::transaction(function () use ($user, $data): MoneyLoan {
            $type = $data['type'] instanceof MoneyLoanType
                ? $data['type']
                : MoneyLoanType::from((string) $data['type']);
            $status = isset($data['status'])
                ? ($data['status'] instanceof MoneyLoanStatus
                    ? $data['status']
                    : MoneyLoanStatus::from((string) $data['status']))
                : MoneyLoanStatus::Active;

            /** @var MoneyLoan $loan */
            $loan = MoneyLoan::query()->withoutUserScope()->create([
                'user_id' => $user->id,
                'name' => (string) $data['name'],
                'type' => $type,
                'lender_counterparty_id' => $data['lender_counterparty_id'] ?? null,
                'currency_code' => $data['currency_code'] ?? 'JPY',
                'original_principal_minor' => $data['original_principal_minor'] ?? null,
                'outstanding_principal_minor' => (int) $data['outstanding_principal_minor'],
                'annual_interest_rate_bps' => $data['annual_interest_rate_bps'] ?? null,
                'monthly_payment_minor' => (int) $data['monthly_payment_minor'],
                'minimum_payment_minor' => $data['minimum_payment_minor'] ?? null,
                'next_payment_on' => $data['next_payment_on'],
                'maturity_on' => $data['maturity_on'] ?? null,
                'prepayment_allowed' => $data['prepayment_allowed'] ?? true,
                'payment_account_id' => $data['payment_account_id'] ?? null,
                'status' => $status,
                'memo' => $data['memo'] ?? null,
                'balance_as_of' => $data['balance_as_of'] ?? Date::now(),
                'lock_version' => 1,
            ]);

            $this->auditService->record(
                (int) $user->id,
                'money_loan.created',
                MoneyLoan::class,
                (string) $loan->id,
                null,
                [
                    'id' => $loan->id,
                    'name' => $loan->name,
                    'type' => $loan->type->value,
                    'amount_minor' => $loan->outstanding_principal_minor,
                    'lock_version' => $loan->lock_version,
                ],
            );

            return $loan;
        });
    }

    public function updateBalance(
        User $user,
        MoneyLoan $loan,
        int $outstandingPrincipalMinor,
        int $lockVersion,
        ?string $note = null,
    ): MoneyLoan {
        $this->assertOwned($user, $loan);

        return DB::transaction(function () use ($user, $loan, $outstandingPrincipalMinor, $lockVersion, $note): MoneyLoan {
            /** @var MoneyLoan|null $locked */
            $locked = MoneyLoan::query()
                ->withoutUserScope()
                ->whereKey($loan->id)
                ->lockForUpdate()
                ->first();

            abort_unless($locked !== null, 404);

            if ((int) $locked->lock_version !== $lockVersion) {
                abort(409, 'Loan lock version mismatch.');
            }

            if ($outstandingPrincipalMinor < 0) {
                throw new InvalidArgumentException('Outstanding principal must be non-negative.');
            }

            $before = [
                'id' => $locked->id,
                'amount_minor' => $locked->outstanding_principal_minor,
                'status' => $locked->status->value,
                'lock_version' => $locked->lock_version,
            ];

            $locked->outstanding_principal_minor = $outstandingPrincipalMinor;
            $locked->balance_as_of = Date::now();
            $locked->lock_version = (int) $locked->lock_version + 1;
            if ($outstandingPrincipalMinor === 0) {
                $locked->status = MoneyLoanStatus::PaidOff;
            }
            $locked->save();

            $this->auditService->record(
                (int) $user->id,
                'money_loan.balance_updated',
                MoneyLoan::class,
                (string) $locked->id,
                $before,
                [
                    'id' => $locked->id,
                    'amount_minor' => $locked->outstanding_principal_minor,
                    'status' => $locked->status->value,
                    'lock_version' => $locked->lock_version,
                    'note' => $note,
                ],
            );

            return $locked->refresh();
        });
    }

    /**
     * @param  array{
     *     due_on: string,
     *     total_minor: int,
     *     principal_minor?: int|null,
     *     interest_minor?: int|null,
     *     fee_minor?: int|null,
     *     create_cashflow?: bool,
     *     create_transaction?: bool,
     *     source?: string|MoneyTransactionSource,
     *     lock_version: int
     * }  $data
     * @return array{
     *     loan: MoneyLoan,
     *     payment: MoneyLoanPayment,
     *     cashflow: MoneyCashflow|null,
     *     transaction: MoneyTransaction|null
     * }
     */
    public function recordPayment(User $user, MoneyLoan $loan, array $data): array
    {
        $this->assertOwned($user, $loan);

        return DB::transaction(function () use ($user, $loan, $data): array {
            /** @var MoneyLoan|null $locked */
            $locked = MoneyLoan::query()
                ->withoutUserScope()
                ->whereKey($loan->id)
                ->lockForUpdate()
                ->first();

            abort_unless($locked !== null, 404);

            if ((int) $locked->lock_version !== (int) $data['lock_version']) {
                abort(409, 'Loan lock version mismatch.');
            }

            $totalMinor = (int) $data['total_minor'];
            if ($totalMinor <= 0) {
                throw new InvalidArgumentException('Payment total must be positive.');
            }

            $principalMinor = array_key_exists('principal_minor', $data) && $data['principal_minor'] !== null
                ? (int) $data['principal_minor']
                : null;
            $interestMinor = array_key_exists('interest_minor', $data) && $data['interest_minor'] !== null
                ? (int) $data['interest_minor']
                : null;
            $feeMinor = array_key_exists('fee_minor', $data) && $data['fee_minor'] !== null
                ? (int) $data['fee_minor']
                : null;

            $parts = array_filter([$principalMinor, $interestMinor, $feeMinor], fn ($v) => $v !== null);
            if ($parts !== [] && array_sum($parts) !== $totalMinor) {
                throw new InvalidArgumentException('Payment parts must sum to total_minor.');
            }

            $createCashflow = (bool) ($data['create_cashflow'] ?? false);
            $createTransaction = (bool) ($data['create_transaction'] ?? false);
            $source = isset($data['source'])
                ? ($data['source'] instanceof MoneyTransactionSource
                    ? $data['source']
                    : MoneyTransactionSource::from((string) $data['source']))
                : MoneyTransactionSource::Manual;

            $cashflow = null;
            if ($createCashflow) {
                $cashflow = $this->cashflowService->create($user, [
                    'direction' => MoneyDirection::Outflow,
                    'kind' => MoneyCashflowKind::LoanPayment,
                    'name' => $locked->name.' 返済 '.$data['due_on'],
                    'amount_minor' => $totalMinor,
                    'currency_code' => $locked->currency_code,
                    'due_on' => $data['due_on'],
                    'status' => MoneyCashflowStatus::Settled,
                    'certainty' => MoneyCertainty::Confirmed,
                    'settlement_account_id' => $locked->payment_account_id,
                    'loan_id' => $locked->id,
                    'flexibility' => MoneyFlexibility::Required,
                    'priority' => MoneyPriority::High,
                ]);
                $cashflow->settled_at = Date::now();
                $cashflow->save();
            }

            $transaction = null;
            if ($createTransaction) {
                $transaction = MoneyTransaction::query()->withoutUserScope()->create([
                    'user_id' => $user->id,
                    'account_id' => $locked->payment_account_id,
                    'direction' => MoneyDirection::Outflow,
                    'kind' => MoneyTransactionKind::LoanPayment,
                    'amount_minor' => $totalMinor,
                    'currency_code' => $locked->currency_code,
                    'occurred_on' => $data['due_on'],
                    'posted_on' => $data['due_on'],
                    'description_raw' => $locked->name.' 返済',
                    'description_normalized' => mb_strtolower($locked->name.' 返済'),
                    'loan_id' => $locked->id,
                    'status' => MoneyTransactionStatus::Posted,
                    'source' => $source,
                ]);
            }

            $appliedPrincipal = $principalMinor ?? min($totalMinor, (int) $locked->outstanding_principal_minor);
            $balanceAfter = max(0, (int) $locked->outstanding_principal_minor - $appliedPrincipal);

            /** @var MoneyLoanPayment $payment */
            $payment = MoneyLoanPayment::query()->withoutUserScope()->create([
                'user_id' => $user->id,
                'loan_id' => $locked->id,
                'due_on' => $data['due_on'],
                'cashflow_id' => $cashflow?->id,
                'transaction_id' => $transaction?->id,
                'total_minor' => $totalMinor,
                'principal_minor' => $principalMinor,
                'interest_minor' => $interestMinor,
                'fee_minor' => $feeMinor,
                'balance_after_minor' => $balanceAfter,
                'status' => 'posted',
                'source' => $source,
            ]);

            $locked->outstanding_principal_minor = $balanceAfter;
            $locked->balance_as_of = Date::now();
            $locked->lock_version = (int) $locked->lock_version + 1;
            if ($balanceAfter === 0) {
                $locked->status = MoneyLoanStatus::PaidOff;
            }
            $locked->save();

            $this->auditService->record(
                (int) $user->id,
                'money_loan.payment_recorded',
                MoneyLoan::class,
                (string) $locked->id,
                null,
                [
                    'id' => $locked->id,
                    'amount_minor' => $totalMinor,
                    'cashflow_id' => $cashflow?->id,
                    'transaction_id' => $transaction?->id,
                    'lock_version' => $locked->lock_version,
                ],
            );

            return [
                'loan' => $locked->refresh(),
                'payment' => $payment,
                'cashflow' => $cashflow,
                'transaction' => $transaction,
            ];
        });
    }

    public function estimatePayoffMonths(MoneyLoan $loan): ?int
    {
        $result = $this->interestEstimator->estimate(
            (int) $loan->outstanding_principal_minor,
            $loan->annual_interest_rate_bps !== null ? (int) $loan->annual_interest_rate_bps : null,
            (int) $loan->monthly_payment_minor,
        );

        return $result['months'] ?? null;
    }

    /**
     * @return array{
     *     baseline_months: int|null,
     *     baseline_interest_minor: string|null,
     *     with_prepay_months: int|null,
     *     with_prepay_interest_minor: string|null,
     *     months_saved: int|null,
     *     interest_saved_minor: string|null
     * }
     */
    public function prepayPreview(MoneyLoan $loan, int $extraPrincipalMinor): array
    {
        $preview = $this->interestEstimator->prepayPreview(
            (int) $loan->outstanding_principal_minor,
            $loan->annual_interest_rate_bps !== null ? (int) $loan->annual_interest_rate_bps : null,
            (int) $loan->monthly_payment_minor,
            $extraPrincipalMinor,
        );

        return [
            'baseline_months' => $preview['baseline_months'],
            'baseline_interest_minor' => $preview['baseline_interest_minor'] !== null
                ? MoneyAmount::ofMinor($preview['baseline_interest_minor'])->toString()
                : null,
            'with_prepay_months' => $preview['with_prepay_months'],
            'with_prepay_interest_minor' => $preview['with_prepay_interest_minor'] !== null
                ? MoneyAmount::ofMinor($preview['with_prepay_interest_minor'])->toString()
                : null,
            'months_saved' => $preview['months_saved'],
            'interest_saved_minor' => $preview['interest_saved_minor'] !== null
                ? MoneyAmount::ofMinor($preview['interest_saved_minor'])->toString()
                : null,
        ];
    }

    /**
     * @return Collection<int, MoneyLoan>
     */
    public function listForUser(User $user): Collection
    {
        return MoneyLoan::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get();
    }

    private function assertOwned(User $user, MoneyLoan $loan): void
    {
        abort_unless((int) $loan->user_id === (int) $user->id, 404);
    }
}
