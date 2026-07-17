<?php

namespace App\Domain\Yoyu\Money\Services;

use App\Domain\Yoyu\Money\Models\MoneyAccount;
use App\Domain\Yoyu\Money\Models\MoneyCashflow;
use App\Domain\Yoyu\Money\Models\MoneyCategory;
use App\Domain\Yoyu\Money\Models\MoneyCreditCard;
use App\Domain\Yoyu\Money\Models\MoneyDecision;
use App\Domain\Yoyu\Money\Models\MoneyLoan;
use App\Domain\Yoyu\Money\Models\MoneySetting;
use App\Domain\Yoyu\Money\Models\MoneyTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Date;

/**
 * Versioned JSON export of allowlisted money tables.
 * Omits CSV raw, storage paths, and audit before/after dumps.
 */
final class MoneyExportService
{
    public const VERSION = 1;

    /**
     * @return array{
     *     version: int,
     *     exported_at: string,
     *     user_id: int,
     *     tables: array<string, list<array<string, mixed>>>
     * }
     */
    public function export(User $user): array
    {
        return [
            'version' => self::VERSION,
            'exported_at' => Date::now()->toIso8601String(),
            'user_id' => (int) $user->id,
            'tables' => [
                'settings' => $this->exportSettings($user),
                'accounts' => $this->exportAccounts($user),
                'categories' => $this->exportCategories($user),
                'cashflows' => $this->exportCashflows($user),
                'transactions' => $this->exportTransactions($user),
                'cards' => $this->exportCards($user),
                'loans' => $this->exportLoans($user),
                'decisions' => $this->exportDecisions($user),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportSettings(User $user): array
    {
        return MoneySetting::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->get([
                'id',
                'currency_code',
                'minimum_living_budget_minor',
                'safety_buffer_minor',
                'uncertain_outflow_reserve_bps',
                'include_expected_income',
                'calculation_horizon_months',
                'formula_version',
                'created_at',
                'updated_at',
            ])
            ->map(fn (MoneySetting $row): array => $row->toArray())
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportAccounts(User $user): array
    {
        return MoneyAccount::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->get([
                'id',
                'name',
                'type',
                'currency_code',
                'current_balance_minor',
                'available_balance_minor',
                'balance_as_of',
                'identifier_last4',
                'memo',
                'is_active',
                'lock_version',
                'created_at',
                'updated_at',
            ])
            ->map(fn (MoneyAccount $row): array => $row->toArray())
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportCategories(User $user): array
    {
        return MoneyCategory::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->orderBy('sort_order')
            ->get([
                'id',
                'parent_id',
                'name',
                'direction_scope',
                'flexibility_default',
                'cost_behavior_default',
                'is_essential',
                'sort_order',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->map(fn (MoneyCategory $row): array => $row->toArray())
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportCashflows(User $user): array
    {
        return MoneyCashflow::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->orderBy('due_on')
            ->get([
                'id',
                'direction',
                'kind',
                'name',
                'amount_minor',
                'currency_code',
                'due_on',
                'original_due_on',
                'status',
                'certainty',
                'category_id',
                'counterparty_id',
                'settlement_account_id',
                'credit_card_id',
                'loan_id',
                'recurring_rule_id',
                'occurrence_on',
                'flexibility',
                'priority',
                'memo',
                'settled_at',
                'lock_version',
                'created_at',
                'updated_at',
            ])
            ->map(fn (MoneyCashflow $row): array => $row->toArray())
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportTransactions(User $user): array
    {
        // Exclude import storage paths / CSV raw — never select import path fields
        // (they live on MoneyImport, not transactions). Omit import_row linkage bodies.
        return MoneyTransaction::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->orderBy('occurred_on')
            ->get([
                'id',
                'account_id',
                'direction',
                'kind',
                'amount_minor',
                'currency_code',
                'occurred_on',
                'posted_on',
                'description_raw',
                'description_normalized',
                'category_id',
                'counterparty_id',
                'credit_card_id',
                'loan_id',
                'payment_method',
                'status',
                'source',
                'external_id',
                'memo',
                'edited_at',
                'voided_at',
                'void_reason',
                'created_at',
                'updated_at',
            ])
            ->map(fn (MoneyTransaction $row): array => $row->toArray())
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportCards(User $user): array
    {
        return MoneyCreditCard::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->get([
                'id',
                'name',
                'issuer_name',
                'identifier_last4',
                'currency_code',
                'closing_day',
                'payment_day',
                'payment_month_offset',
                'payment_account_id',
                'limit_minor',
                'available_minor',
                'current_statement_minor',
                'unconfirmed_minor',
                'revolving_balance_minor',
                'installment_balance_minor',
                'revolving_fee_rate_bps',
                'minimum_payment_minor',
                'default_payment_type',
                'snapshot_as_of',
                'is_active',
                'lock_version',
                'created_at',
                'updated_at',
            ])
            ->map(fn (MoneyCreditCard $row): array => $row->toArray())
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportLoans(User $user): array
    {
        return MoneyLoan::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->get([
                'id',
                'name',
                'type',
                'lender_counterparty_id',
                'currency_code',
                'original_principal_minor',
                'outstanding_principal_minor',
                'annual_interest_rate_bps',
                'monthly_payment_minor',
                'minimum_payment_minor',
                'next_payment_on',
                'maturity_on',
                'prepayment_allowed',
                'payment_account_id',
                'status',
                'memo',
                'balance_as_of',
                'lock_version',
                'created_at',
                'updated_at',
            ])
            ->map(fn (MoneyLoan $row): array => $row->toArray())
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportDecisions(User $user): array
    {
        return MoneyDecision::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->orderByDesc('decided_on')
            ->get([
                'id',
                'title',
                'decided_on',
                'status',
                'simulation_id',
                'before_payload',
                'expected_effect_payload',
                'actual_effect_payload',
                'memo',
                'reviewed_at',
                'created_at',
                'updated_at',
            ])
            ->map(fn (MoneyDecision $row): array => $row->toArray())
            ->all();
    }
}
