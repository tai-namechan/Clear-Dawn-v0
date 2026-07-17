<?php

namespace App\Domain\Yoyu\Money\Services;

use App\Domain\Yoyu\Money\Enums\MoneyCardStatementStatus;
use App\Domain\Yoyu\Money\Enums\MoneyCashflowKind;
use App\Domain\Yoyu\Money\Enums\MoneyCashflowStatus;
use App\Domain\Yoyu\Money\Enums\MoneyCertainty;
use App\Domain\Yoyu\Money\Enums\MoneyDirection;
use App\Domain\Yoyu\Money\Enums\MoneyFlexibility;
use App\Domain\Yoyu\Money\Enums\MoneyPriority;
use App\Domain\Yoyu\Money\Enums\MoneyTransactionSource;
use App\Domain\Yoyu\Money\Models\MoneyCardSnapshot;
use App\Domain\Yoyu\Money\Models\MoneyCardStatement;
use App\Domain\Yoyu\Money\Models\MoneyCashflow;
use App\Domain\Yoyu\Money\Models\MoneyCreditCard;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class MoneyCardService
{
    public function __construct(
        private readonly MoneyAuditService $auditService,
        private readonly MoneyCashflowService $cashflowService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): MoneyCreditCard
    {
        return DB::transaction(function () use ($user, $data): MoneyCreditCard {
            /** @var MoneyCreditCard $card */
            $card = MoneyCreditCard::query()->withoutUserScope()->create([
                'user_id' => $user->id,
                'name' => (string) $data['name'],
                'issuer_name' => $data['issuer_name'] ?? null,
                'identifier_last4' => $data['identifier_last4'] ?? null,
                'currency_code' => $data['currency_code'] ?? 'JPY',
                'closing_day' => (string) $data['closing_day'],
                'payment_day' => (string) $data['payment_day'],
                'payment_month_offset' => (int) ($data['payment_month_offset'] ?? 1),
                'payment_account_id' => $data['payment_account_id'] ?? null,
                'limit_minor' => $data['limit_minor'] ?? null,
                'available_minor' => $data['available_minor'] ?? null,
                'current_statement_minor' => $data['current_statement_minor'] ?? null,
                'unconfirmed_minor' => $data['unconfirmed_minor'] ?? null,
                'revolving_balance_minor' => $data['revolving_balance_minor'] ?? null,
                'installment_balance_minor' => $data['installment_balance_minor'] ?? null,
                'revolving_fee_rate_bps' => $data['revolving_fee_rate_bps'] ?? null,
                'minimum_payment_minor' => $data['minimum_payment_minor'] ?? null,
                'default_payment_type' => $data['default_payment_type'] ?? 'lump_sum',
                'snapshot_as_of' => $data['snapshot_as_of'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'lock_version' => 1,
            ]);

            $this->auditService->record(
                (int) $user->id,
                'money_credit_card.created',
                MoneyCreditCard::class,
                (string) $card->id,
                null,
                [
                    'id' => $card->id,
                    'name' => $card->name,
                    'lock_version' => $card->lock_version,
                ],
            );

            return $card;
        });
    }

    /**
     * @param  array{
     *     limit_minor?: int|null,
     *     available_minor?: int|null,
     *     current_statement_minor?: int|null,
     *     unconfirmed_minor?: int|null,
     *     revolving_balance_minor?: int|null,
     *     installment_balance_minor?: int|null,
     *     minimum_payment_minor?: int|null,
     *     observed_at?: string|\DateTimeInterface|null,
     *     source?: string|MoneyTransactionSource,
     *     lock_version: int
     * }  $data
     */
    public function updateSnapshot(User $user, MoneyCreditCard $card, array $data): MoneyCreditCard
    {
        $this->assertOwned($user, $card);

        return DB::transaction(function () use ($user, $card, $data): MoneyCreditCard {
            /** @var MoneyCreditCard|null $locked */
            $locked = MoneyCreditCard::query()
                ->withoutUserScope()
                ->whereKey($card->id)
                ->lockForUpdate()
                ->first();

            abort_unless($locked !== null, 404);

            if ((int) $locked->lock_version !== (int) $data['lock_version']) {
                abort(409, 'Credit card lock version mismatch.');
            }

            $observedAt = isset($data['observed_at'])
                ? Date::parse($data['observed_at'])
                : Date::now();
            $source = isset($data['source'])
                ? ($data['source'] instanceof MoneyTransactionSource
                    ? $data['source']
                    : MoneyTransactionSource::from((string) $data['source']))
                : MoneyTransactionSource::Manual;

            $fields = [
                'limit_minor',
                'available_minor',
                'current_statement_minor',
                'unconfirmed_minor',
                'revolving_balance_minor',
                'installment_balance_minor',
                'minimum_payment_minor',
            ];

            $snapshotAttrs = [
                'user_id' => $user->id,
                'credit_card_id' => $locked->id,
                'observed_at' => $observedAt,
                'source' => $source,
            ];

            $before = [
                'id' => $locked->id,
                'lock_version' => $locked->lock_version,
            ];

            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    $snapshotAttrs[$field] = $data[$field];
                    $locked->{$field} = $data[$field];
                    $before[$field] = $card->{$field};
                } else {
                    $snapshotAttrs[$field] = $locked->{$field};
                }
            }

            MoneyCardSnapshot::query()->withoutUserScope()->create($snapshotAttrs);

            $locked->snapshot_as_of = $observedAt;
            $locked->lock_version = (int) $locked->lock_version + 1;
            $locked->save();

            $this->auditService->record(
                (int) $user->id,
                'money_credit_card.snapshot_updated',
                MoneyCreditCard::class,
                (string) $locked->id,
                $before,
                [
                    'id' => $locked->id,
                    'lock_version' => $locked->lock_version,
                    'available_minor' => $locked->available_minor,
                    'current_statement_minor' => $locked->current_statement_minor,
                ],
            );

            return $locked->refresh();
        });
    }

    /**
     * @param  array{
     *     period_start: string,
     *     period_end: string,
     *     due_on: string,
     *     amount_minor: int,
     *     closed_on?: string|null,
     *     status?: string|MoneyCardStatementStatus,
     *     source?: string|MoneyTransactionSource
     * }  $data
     */
    public function createOrReviseStatement(
        User $user,
        MoneyCreditCard $card,
        array $data,
    ): MoneyCardStatement {
        $this->assertOwned($user, $card);

        return DB::transaction(function () use ($user, $card, $data): MoneyCardStatement {
            $periodEnd = (string) $data['period_end'];
            $amountMinor = (int) $data['amount_minor'];
            if ($amountMinor < 0) {
                throw new InvalidArgumentException('Statement amount must be non-negative.');
            }

            $source = isset($data['source'])
                ? ($data['source'] instanceof MoneyTransactionSource
                    ? $data['source']
                    : MoneyTransactionSource::from((string) $data['source']))
                : MoneyTransactionSource::Manual;

            $status = isset($data['status'])
                ? ($data['status'] instanceof MoneyCardStatementStatus
                    ? $data['status']
                    : MoneyCardStatementStatus::from((string) $data['status']))
                : MoneyCardStatementStatus::Closed;

            /** @var MoneyCardStatement|null $latest */
            $latest = MoneyCardStatement::query()
                ->withoutUserScope()
                ->where('credit_card_id', $card->id)
                ->whereDate('period_end', $periodEnd)
                ->where('status', '!=', MoneyCardStatementStatus::Superseded->value)
                ->orderByDesc('revision')
                ->lockForUpdate()
                ->first();

            $revision = 1;
            $supersedesId = null;

            if ($latest !== null) {
                $revision = (int) $latest->revision + 1;
                $supersedesId = $latest->id;

                if ($latest->cashflow_id !== null) {
                    /** @var MoneyCashflow|null $oldCashflow */
                    $oldCashflow = MoneyCashflow::query()
                        ->withoutUserScope()
                        ->whereKey($latest->cashflow_id)
                        ->first();

                    if ($oldCashflow !== null
                        && ! in_array($oldCashflow->status, [
                            MoneyCashflowStatus::Settled,
                            MoneyCashflowStatus::Canceled,
                            MoneyCashflowStatus::Deferred,
                        ], true)
                    ) {
                        $this->cashflowService->cancel($user, $oldCashflow, (int) $oldCashflow->lock_version);
                    }
                }

                $latest->status = MoneyCardStatementStatus::Superseded;
                $latest->save();
            }

            $cashflow = $this->cashflowService->create($user, [
                'direction' => MoneyDirection::Outflow,
                'kind' => MoneyCashflowKind::CardStatement,
                'name' => $card->name.' カード請求 '.$periodEnd,
                'amount_minor' => $amountMinor,
                'currency_code' => $card->currency_code,
                'due_on' => $data['due_on'],
                'status' => MoneyCashflowStatus::Planned,
                'certainty' => MoneyCertainty::Confirmed,
                'settlement_account_id' => $card->payment_account_id,
                'credit_card_id' => $card->id,
                'flexibility' => MoneyFlexibility::Required,
                'priority' => MoneyPriority::High,
            ]);

            /** @var MoneyCardStatement $statement */
            $statement = MoneyCardStatement::query()->withoutUserScope()->create([
                'user_id' => $user->id,
                'credit_card_id' => $card->id,
                'period_start' => $data['period_start'],
                'period_end' => $periodEnd,
                'closed_on' => $data['closed_on'] ?? $periodEnd,
                'due_on' => $data['due_on'],
                'amount_minor' => $amountMinor,
                'status' => $status,
                'revision' => $revision,
                'cashflow_id' => $cashflow->id,
                'source' => $source,
                'supersedes_id' => $supersedesId,
            ]);

            $this->auditService->record(
                (int) $user->id,
                'money_card_statement.created',
                MoneyCardStatement::class,
                (string) $statement->id,
                null,
                [
                    'id' => $statement->id,
                    'credit_card_id' => $card->id,
                    'amount_minor' => $statement->amount_minor,
                    'status' => $statement->status->value,
                    'cashflow_id' => $cashflow->id,
                    'supersedes_id' => $supersedesId,
                ],
            );

            return $statement;
        });
    }

    /**
     * @return Collection<int, MoneyCreditCard>
     */
    public function listForUser(User $user): Collection
    {
        return MoneyCreditCard::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get();
    }

    private function assertOwned(User $user, MoneyCreditCard $card): void
    {
        abort_unless((int) $card->user_id === (int) $user->id, 404);
    }
}
