<?php

namespace App\Domain\Yoyu\Money\Services;

use App\Domain\Yoyu\Money\Enums\MoneyDirection;
use App\Domain\Yoyu\Money\Enums\MoneyTransactionKind;
use App\Domain\Yoyu\Money\Enums\MoneyTransactionSource;
use App\Domain\Yoyu\Money\Enums\MoneyTransactionStatus;
use App\Domain\Yoyu\Money\Models\MoneyAccount;
use App\Domain\Yoyu\Money\Models\MoneyTransaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class MoneyTransactionService
{
    public function __construct(
        private readonly MoneyAuditService $auditService,
    ) {}

    /**
     * @return LengthAwarePaginator<int, MoneyTransaction>
     */
    public function paginateForUser(User $user, int $perPage = 50): LengthAwarePaginator
    {
        return MoneyTransaction::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->orderByDesc('occurred_on')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createManual(User $user, array $data): MoneyTransaction
    {
        $accountId = (string) $data['account_id'];

        /** @var MoneyAccount|null $account */
        $account = MoneyAccount::query()
            ->withoutUserScope()
            ->whereKey($accountId)
            ->where('user_id', $user->id)
            ->first();

        abort_unless($account !== null, 404);

        $direction = $data['direction'] instanceof MoneyDirection
            ? $data['direction']
            : MoneyDirection::from((string) $data['direction']);
        $kind = $data['kind'] instanceof MoneyTransactionKind
            ? $data['kind']
            : MoneyTransactionKind::from((string) $data['kind']);

        $amountMinor = (int) $data['amount_minor'];
        if ($amountMinor < 0) {
            throw new InvalidArgumentException('Transaction amount must be non-negative.');
        }

        return DB::transaction(function () use ($user, $data, $account, $direction, $kind, $amountMinor): MoneyTransaction {
            /** @var MoneyTransaction $transaction */
            $transaction = MoneyTransaction::query()->withoutUserScope()->create([
                'user_id' => $user->id,
                'account_id' => $account->id,
                'direction' => $direction,
                'kind' => $kind,
                'amount_minor' => $amountMinor,
                'currency_code' => $data['currency_code'] ?? 'JPY',
                'occurred_on' => $data['occurred_on'],
                'posted_on' => $data['posted_on'] ?? $data['occurred_on'],
                'description_raw' => $data['description_raw'] ?? $data['description'] ?? null,
                'description_normalized' => $data['description_normalized']
                    ?? $data['description_raw']
                    ?? $data['description']
                    ?? null,
                'category_id' => $data['category_id'] ?? null,
                'counterparty_id' => $data['counterparty_id'] ?? null,
                'credit_card_id' => $data['credit_card_id'] ?? null,
                'loan_id' => $data['loan_id'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'cost_behavior' => $data['cost_behavior'] ?? null,
                'status' => MoneyTransactionStatus::Posted,
                'source' => MoneyTransactionSource::Manual,
                'memo' => $data['memo'] ?? null,
            ]);

            $this->auditService->record(
                (int) $user->id,
                'money_transaction.created',
                MoneyTransaction::class,
                (string) $transaction->id,
                null,
                [
                    'id' => $transaction->id,
                    'amount_minor' => $transaction->amount_minor,
                    'direction' => $transaction->direction->value,
                    'kind' => $transaction->kind->value,
                ],
            );

            return $transaction;
        });
    }

    public function void(User $user, MoneyTransaction $transaction, ?string $reason = null): MoneyTransaction
    {
        $this->assertOwned($user, $transaction);

        if ($transaction->voided_at !== null
            || $transaction->status === MoneyTransactionStatus::Voided) {
            throw new InvalidArgumentException('Transaction is already voided.');
        }

        return DB::transaction(function () use ($user, $transaction, $reason): MoneyTransaction {
            $before = [
                'id' => $transaction->id,
                'status' => $transaction->status->value,
                'voided_at' => $transaction->voided_at?->toIso8601String(),
            ];

            $transaction->status = MoneyTransactionStatus::Voided;
            $transaction->voided_at = Date::now();
            $transaction->void_reason = $reason ?? 'manual_void';
            $transaction->save();

            $this->auditService->record(
                (int) $user->id,
                'money_transaction.voided',
                MoneyTransaction::class,
                (string) $transaction->id,
                $before,
                [
                    'id' => $transaction->id,
                    'status' => $transaction->status->value,
                    'void_reason' => $transaction->void_reason,
                ],
            );

            return $transaction->refresh();
        });
    }

    private function assertOwned(User $user, MoneyTransaction $transaction): void
    {
        abort_unless((int) $transaction->user_id === (int) $user->id, 404);
    }
}
