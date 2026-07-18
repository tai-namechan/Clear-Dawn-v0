<?php

namespace App\Domain\Yoyu\Money\Services;

use App\Domain\Yoyu\Money\Enums\MoneyTransactionKind;
use App\Domain\Yoyu\Money\Enums\MoneyTransactionStatus;
use App\Domain\Yoyu\Money\Models\MoneyLoanPayment;
use App\Domain\Yoyu\Money\Models\MoneyTransaction;
use App\Domain\Yoyu\Money\Support\MoneyAmount;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class MoneyAnalysisQuery
{
    /**
     * Aggregate spending for analysis. Excludes card_payment, transfer, and
     * loan_payment principal (interest/fee remain when principal is known).
     *
     * @param  array{
     *     category_id?: string|null,
     *     counterparty_id?: string|null,
     *     account_id?: string|null
     * }  $filters
     * @return array{
     *     from: string,
     *     to: string,
     *     total_spend_minor: string,
     *     monthly: list<array{year_month: string, amount_minor: string}>,
     *     by_category: list<array{category_id: string|null, amount_minor: string}>,
     *     by_counterparty: list<array{counterparty_id: string|null, amount_minor: string}>
     * }
     */
    public function analyze(User $user, string $from, string $to, array $filters = []): array
    {
        $fromDate = CarbonImmutable::parse($from)->toDateString();
        $toDate = CarbonImmutable::parse($to)->toDateString();

        $query = MoneyTransaction::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->where('status', MoneyTransactionStatus::Posted->value)
            ->whereNull('voided_at')
            ->whereDate('occurred_on', '>=', $fromDate)
            ->whereDate('occurred_on', '<=', $toDate)
            ->when(
                isset($filters['category_id']) && $filters['category_id'] !== null,
                fn ($q) => $q->where('category_id', $filters['category_id']),
            )
            ->when(
                isset($filters['counterparty_id']) && $filters['counterparty_id'] !== null,
                fn ($q) => $q->where('counterparty_id', $filters['counterparty_id']),
            )
            ->when(
                isset($filters['account_id']) && $filters['account_id'] !== null,
                fn ($q) => $q->where('account_id', $filters['account_id']),
            );

        /** @var Collection<int, MoneyTransaction> $transactions */
        $transactions = $query->get();

        $loanPrincipalByTx = MoneyLoanPayment::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->whereNotNull('transaction_id')
            ->whereNotNull('principal_minor')
            ->whereIn('transaction_id', $transactions->pluck('id')->all())
            ->pluck('principal_minor', 'transaction_id');

        $monthly = [];
        $byCategory = [];
        $byCounterparty = [];
        $total = 0;

        foreach ($transactions as $transaction) {
            $contribution = $this->spendingContributionMinor($transaction, $loanPrincipalByTx);
            if ($contribution === null) {
                continue;
            }

            $total += $contribution;

            $month = CarbonImmutable::parse((string) $transaction->occurred_on->toDateString())->format('Y-m');
            $monthly[$month] = ($monthly[$month] ?? 0) + $contribution;

            $categoryKey = $transaction->category_id ?? '';
            $byCategory[$categoryKey] = ($byCategory[$categoryKey] ?? 0) + $contribution;

            $counterpartyKey = $transaction->counterparty_id ?? '';
            $byCounterparty[$counterpartyKey] = ($byCounterparty[$counterpartyKey] ?? 0) + $contribution;
        }

        ksort($monthly);

        return [
            'from' => $fromDate,
            'to' => $toDate,
            'total_spend_minor' => MoneyAmount::ofMinor($total)->toString(),
            'monthly' => array_map(
                fn (string $ym, int $amount): array => [
                    'year_month' => $ym,
                    'amount_minor' => MoneyAmount::ofMinor($amount)->toString(),
                ],
                array_keys($monthly),
                array_values($monthly),
            ),
            'by_category' => $this->mapKeyedAmounts($byCategory, 'category_id'),
            'by_counterparty' => $this->mapKeyedAmounts($byCounterparty, 'counterparty_id'),
        ];
    }

    /**
     * @param  Collection<string, int|string>  $loanPrincipalByTx
     */
    private function spendingContributionMinor(
        MoneyTransaction $transaction,
        Collection $loanPrincipalByTx,
    ): ?int {
        $kind = $transaction->kind;

        if ($kind === MoneyTransactionKind::CardPayment
            || $kind === MoneyTransactionKind::Transfer
            || $kind === MoneyTransactionKind::Income
            || $kind === MoneyTransactionKind::Adjustment
        ) {
            return null;
        }

        $amount = (int) $transaction->amount_minor;

        if ($kind === MoneyTransactionKind::LoanPayment) {
            $principal = $loanPrincipalByTx->get($transaction->id);
            if ($principal === null) {
                // Principal unknown → exclude entire loan payment from spend.
                return null;
            }

            $residual = $amount - (int) $principal;

            return $residual > 0 ? $residual : null;
        }

        if ($kind === MoneyTransactionKind::Refund) {
            return -$amount;
        }

        if (in_array($kind, [
            MoneyTransactionKind::Purchase,
            MoneyTransactionKind::Fee,
            MoneyTransactionKind::Interest,
        ], true)) {
            return $amount;
        }

        return null;
    }

    /**
     * @param  array<string, int>  $keyed
     * @return list<array{category_id?: string|null, counterparty_id?: string|null, amount_minor: string}>
     */
    private function mapKeyedAmounts(array $keyed, string $idKey): array
    {
        arsort($keyed);
        $rows = [];
        foreach ($keyed as $id => $amount) {
            $rows[] = [
                $idKey => $id === '' ? null : $id,
                'amount_minor' => MoneyAmount::ofMinor($amount)->toString(),
            ];
        }

        return $rows;
    }
}
