<?php

namespace App\Domain\Yoyu\Money\Services;

use App\Domain\Yoyu\Money\Enums\MoneyCashflowStatus;
use App\Domain\Yoyu\Money\Enums\MoneyDirection;
use App\Domain\Yoyu\Money\Models\MoneyAccount;
use App\Domain\Yoyu\Money\Models\MoneyCashflow;
use App\Domain\Yoyu\Money\Models\MoneyCreditCard;
use App\Domain\Yoyu\Money\Models\MoneyLoan;
use App\Domain\Yoyu\Money\Models\MoneySetting;
use App\Models\User;

/**
 * Derive first-run setup progress from existing Money data (no completion flags).
 */
final class MoneySetupProgressService
{
    /**
     * @return array{
     *     is_complete: bool,
     *     required_complete: bool,
     *     completed_required_count: int,
     *     required_count: int,
     *     next_step_key: string|null,
     *     steps: list<array{
     *         key: string,
     *         label: string,
     *         description: string,
     *         status: 'complete'|'incomplete'|'optional',
     *         href: string,
     *         required: bool
     *     }>
     * }
     */
    public function forUser(User $user, MoneySetting $settings): array
    {
        $hasAccount = MoneyAccount::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();

        $hasIncome = MoneyCashflow::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->where('direction', MoneyDirection::Inflow->value)
            ->whereNotIn('status', [
                MoneyCashflowStatus::Canceled->value,
                MoneyCashflowStatus::Deferred->value,
            ])
            ->exists();

        $hasOutflow = MoneyCashflow::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->where('direction', MoneyDirection::Outflow->value)
            ->whereNotIn('status', [
                MoneyCashflowStatus::Canceled->value,
                MoneyCashflowStatus::Deferred->value,
            ])
            ->exists();

        $hasCardOrLoan = MoneyCreditCard::query()
            ->withoutUserScope()
            ->where('user_id', $user->id)
            ->exists()
            || MoneyLoan::query()
                ->withoutUserScope()
                ->where('user_id', $user->id)
                ->exists();

        $hasReserves = $settings->minimum_living_budget_minor !== null
            && $settings->safety_buffer_minor !== null;

        $steps = [
            [
                'key' => 'accounts',
                'label' => '口座と現在残高',
                'description' => 'いま使っている口座と残高を登録します',
                'status' => $hasAccount ? 'complete' : 'incomplete',
                'href' => '/yoyu/money/accounts?compose=1',
                'required' => true,
            ],
            [
                'key' => 'income',
                'label' => '次の収入予定',
                'description' => '給与など、次に入る予定を登録します',
                'status' => $hasIncome ? 'complete' : 'incomplete',
                'href' => '/yoyu/money/cashflows?compose=income',
                'required' => true,
            ],
            [
                'key' => 'payments',
                'label' => '今月の支払い',
                'description' => '家賃やカード請求など、支払い予定を登録します',
                'status' => $hasOutflow ? 'complete' : 'incomplete',
                'href' => '/yoyu/money/cashflows?compose=expense',
                'required' => true,
            ],
            [
                'key' => 'credit',
                'label' => 'カード・ローン',
                'description' => '必要ならカードやローンを登録します（任意）',
                'status' => $hasCardOrLoan ? 'complete' : 'optional',
                'href' => '/yoyu/money/cards?compose=1',
                'required' => false,
            ],
            [
                'key' => 'reserves',
                'label' => '最低生活費・安全資金',
                'description' => '余裕額の計算に使う前提を設定します',
                'status' => $hasReserves ? 'complete' : 'incomplete',
                'href' => '/yoyu/money/settings',
                'required' => true,
            ],
        ];

        $required = array_values(array_filter($steps, fn (array $step): bool => $step['required']));
        $completedRequired = array_values(array_filter(
            $required,
            fn (array $step): bool => $step['status'] === 'complete',
        ));

        $next = null;
        foreach ($steps as $step) {
            if ($step['required'] && $step['status'] === 'incomplete') {
                $next = $step['key'];
                break;
            }
        }

        $requiredComplete = count($completedRequired) === count($required);

        return [
            'is_complete' => $requiredComplete,
            'required_complete' => $requiredComplete,
            'completed_required_count' => count($completedRequired),
            'required_count' => count($required),
            'next_step_key' => $next,
            'steps' => $steps,
        ];
    }
}
