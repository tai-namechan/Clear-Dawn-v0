<?php

namespace App\Domain\Yoyu\Money\Services;

use App\Domain\Yoyu\Money\Enums\MoneyAccountType;
use App\Domain\Yoyu\Money\Enums\MoneyTransactionSource;
use App\Domain\Yoyu\Money\Models\MoneyAccount;
use App\Domain\Yoyu\Money\Models\MoneyAccountBalanceSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final class MoneyAccountService
{
    public function __construct(
        private readonly MoneyAuditService $auditService,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     type: string|MoneyAccountType,
     *     currency_code?: string,
     *     current_balance_minor?: int,
     *     available_balance_minor?: int|null,
     *     balance_as_of?: string|\DateTimeInterface|null,
     *     identifier_last4?: string|null,
     *     memo?: string|null,
     *     is_active?: bool
     * }  $data
     */
    public function create(User $user, array $data): MoneyAccount
    {
        return DB::transaction(function () use ($user, $data): MoneyAccount {
            $balanceMinor = (int) ($data['current_balance_minor'] ?? 0);
            $availableMinor = array_key_exists('available_balance_minor', $data)
                ? ($data['available_balance_minor'] !== null ? (int) $data['available_balance_minor'] : null)
                : null;
            $observedAt = isset($data['balance_as_of'])
                ? Date::parse($data['balance_as_of'])
                : Date::now();

            /** @var MoneyAccount $account */
            $account = MoneyAccount::query()->withoutUserScope()->create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'type' => $data['type'] instanceof MoneyAccountType
                    ? $data['type']
                    : MoneyAccountType::from((string) $data['type']),
                'currency_code' => $data['currency_code'] ?? 'JPY',
                'current_balance_minor' => $balanceMinor,
                'available_balance_minor' => $availableMinor,
                'balance_as_of' => $observedAt,
                'identifier_last4' => $data['identifier_last4'] ?? null,
                'memo' => $data['memo'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'lock_version' => 1,
            ]);

            MoneyAccountBalanceSnapshot::query()->withoutUserScope()->create([
                'user_id' => $user->id,
                'account_id' => $account->id,
                'balance_minor' => $balanceMinor,
                'available_balance_minor' => $availableMinor,
                'observed_at' => $observedAt,
                'source' => MoneyTransactionSource::Manual,
                'note' => 'initial_balance',
            ]);

            $this->auditService->record(
                (int) $user->id,
                'money_account.created',
                MoneyAccount::class,
                (string) $account->id,
                null,
                [
                    'id' => $account->id,
                    'name' => $account->name,
                    'type' => $account->type->value,
                    'current_balance_minor' => $account->current_balance_minor,
                    'available_balance_minor' => $account->available_balance_minor,
                    'lock_version' => $account->lock_version,
                ],
            );

            return $account;
        });
    }

    public function updateBalance(
        User $user,
        MoneyAccount $account,
        int $balanceMinor,
        ?int $availableMinor,
        ?string $note,
        int $lockVersion,
    ): MoneyAccount {
        $this->assertOwned($user, $account);

        return DB::transaction(function () use ($user, $account, $balanceMinor, $availableMinor, $note, $lockVersion): MoneyAccount {
            /** @var MoneyAccount|null $locked */
            $locked = MoneyAccount::query()
                ->withoutUserScope()
                ->whereKey($account->id)
                ->lockForUpdate()
                ->first();

            abort_unless($locked !== null, 404);

            if ((int) $locked->lock_version !== $lockVersion) {
                abort(409, 'Account lock version mismatch.');
            }

            $before = [
                'id' => $locked->id,
                'current_balance_minor' => $locked->current_balance_minor,
                'available_balance_minor' => $locked->available_balance_minor,
                'lock_version' => $locked->lock_version,
            ];

            $observedAt = Date::now();
            $locked->fill([
                'current_balance_minor' => $balanceMinor,
                'available_balance_minor' => $availableMinor,
                'balance_as_of' => $observedAt,
                'lock_version' => (int) $locked->lock_version + 1,
            ]);
            $locked->save();

            MoneyAccountBalanceSnapshot::query()->withoutUserScope()->create([
                'user_id' => $user->id,
                'account_id' => $locked->id,
                'balance_minor' => $balanceMinor,
                'available_balance_minor' => $availableMinor,
                'observed_at' => $observedAt,
                'source' => MoneyTransactionSource::Manual,
                'note' => $note,
            ]);

            $this->auditService->record(
                (int) $user->id,
                'money_account.balance_updated',
                MoneyAccount::class,
                (string) $locked->id,
                $before,
                [
                    'id' => $locked->id,
                    'current_balance_minor' => $locked->current_balance_minor,
                    'available_balance_minor' => $locked->available_balance_minor,
                    'lock_version' => $locked->lock_version,
                    'note' => $note,
                ],
            );

            return $locked->refresh();
        });
    }

    public function toggleActive(User $user, MoneyAccount $account, bool $isActive): MoneyAccount
    {
        $this->assertOwned($user, $account);

        $before = [
            'id' => $account->id,
            'is_active' => $account->is_active,
        ];

        $account->is_active = $isActive;
        $account->save();

        $this->auditService->record(
            (int) $user->id,
            'money_account.active_toggled',
            MoneyAccount::class,
            (string) $account->id,
            $before,
            [
                'id' => $account->id,
                'is_active' => $account->is_active,
            ],
        );

        return $account->refresh();
    }

    private function assertOwned(User $user, MoneyAccount $account): void
    {
        abort_unless((int) $account->user_id === (int) $user->id, 404);
    }
}
