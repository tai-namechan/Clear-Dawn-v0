<?php

namespace App\Domain\Yoyu\Money\Services;

use App\Domain\Yoyu\Money\Models\MoneyAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;

final class MoneyAuditService
{
    /**
     * Keys permitted in before/after payloads. Raw CSV / file bodies are never logged.
     *
     * @var list<string>
     */
    private const ALLOWLIST_KEYS = [
        'id',
        'user_id',
        'name',
        'type',
        'direction',
        'kind',
        'status',
        'certainty',
        'amount_minor',
        'currency_code',
        'due_on',
        'original_due_on',
        'occurred_on',
        'occurrence_on',
        'balance_minor',
        'current_balance_minor',
        'available_balance_minor',
        'available_minor',
        'balance_as_of',
        'lock_version',
        'is_active',
        'is_essential',
        'category_id',
        'counterparty_id',
        'settlement_account_id',
        'account_id',
        'credit_card_id',
        'loan_id',
        'recurring_rule_id',
        'supersedes_id',
        'payment_method',
        'flexibility',
        'priority',
        'cost_behavior',
        'memo',
        'note',
        'timezone',
        'minimum_living_budget_minor',
        'safety_buffer_minor',
        'uncertain_outflow_reserve_bps',
        'include_expected_income',
        'calculation_horizon_months',
        'formula_version',
        'event_type',
        'source',
        'reconciled_at',
        'settled_at',
        'observed_at',
        'identifier_last4',
        'frequency',
        'interval_count',
        'day_of_month',
        'day_of_week',
        'month_of_year',
        'start_on',
        'end_on',
        'generated_through',
        'cashflow_id',
        'transaction_id',
    ];

    /**
     * @var list<string>
     */
    private const DENY_SUBSTRINGS = [
        'csv',
        'raw_content',
        'file_contents',
        'file_body',
        'payload_raw',
        'row_raw',
        'original_bytes',
        'storage_path',
        'checksum',
    ];

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        int $userId,
        string $eventType,
        string $subjectType,
        string $subjectId,
        ?array $before,
        ?array $after,
        ?string $correlationId = null,
    ): void {
        MoneyAuditEvent::query()->withoutUserScope()->create([
            'user_id' => $userId,
            'actor_user_id' => Auth::id(),
            'event_type' => $eventType,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'before_payload' => $this->sanitizePayload($before),
            'after_payload' => $this->sanitizePayload($after),
            'correlation_id' => $correlationId,
            'occurred_at' => Date::now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    private function sanitizePayload(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        return $this->filterKeys($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function filterKeys(array $payload): array
    {
        $allow = array_fill_keys(self::ALLOWLIST_KEYS, true);
        $filtered = [];

        foreach ($payload as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $normalized = strtolower($key);
            if ($this->isDeniedKey($normalized)) {
                continue;
            }

            if (! isset($allow[$key]) && ! isset($allow[$normalized])) {
                continue;
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $nested = $this->filterKeys($value);
                if ($nested !== []) {
                    $filtered[$key] = $nested;
                }

                continue;
            }

            if (is_scalar($value) || $value === null) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    private function isDeniedKey(string $normalizedKey): bool
    {
        foreach (self::DENY_SUBSTRINGS as $needle) {
            if (str_contains($normalizedKey, $needle)) {
                return true;
            }
        }

        return false;
    }
}
