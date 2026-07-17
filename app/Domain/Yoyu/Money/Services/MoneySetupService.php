<?php

namespace App\Domain\Yoyu\Money\Services;

use App\Domain\Yoyu\Money\Enums\MoneyCostBehavior;
use App\Domain\Yoyu\Money\Enums\MoneyDirectionScope;
use App\Domain\Yoyu\Money\Enums\MoneyFlexibility;
use App\Domain\Yoyu\Money\Models\MoneyCategory;
use App\Domain\Yoyu\Money\Models\MoneySetting;
use App\Domain\Yoyu\Support\UserTimezoneResolver;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class MoneySetupService
{
    public function __construct(
        private readonly UserTimezoneResolver $timezoneResolver,
    ) {}

    public function ensureForUser(User $user): MoneySetting
    {
        /** @var MoneySetting $setting */
        $setting = MoneySetting::query()
            ->withoutUserScope()
            ->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'currency_code' => 'JPY',
                    'uncertain_outflow_reserve_bps' => 10000,
                    'include_expected_income' => false,
                    'calculation_horizon_months' => 3,
                    'formula_version' => '1',
                ],
            );

        return $setting;
    }

    public function seedDefaultCategories(int $userId): void
    {
        $existing = MoneyCategory::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->pluck('name')
            ->all();

        $existingSet = array_fill_keys($existing, true);
        $sortOrder = count($existing);

        foreach ($this->defaultCategoryDefinitions() as $definition) {
            if (isset($existingSet[$definition['name']])) {
                continue;
            }

            MoneyCategory::query()->withoutUserScope()->create([
                'user_id' => $userId,
                'name' => $definition['name'],
                'direction_scope' => $definition['direction_scope'],
                'flexibility_default' => $definition['flexibility_default'],
                'cost_behavior_default' => $definition['cost_behavior_default'],
                'is_essential' => $definition['is_essential'],
                'sort_order' => $sortOrder,
                'is_active' => true,
            ]);

            $existingSet[$definition['name']] = true;
            $sortOrder++;
        }
    }

    /**
     * @param  array{
     *     timezone?: string|null,
     *     minimum_living_budget_minor?: int|null,
     *     safety_buffer_minor?: int|null,
     *     uncertain_outflow_reserve_bps?: int|null,
     *     include_expected_income?: bool|null,
     *     calculation_horizon_months?: int|null,
     *     formula_version?: string|null,
     *     currency_code?: string|null
     * }  $data
     */
    public function setup(User $user, array $data): MoneySetting
    {
        return DB::transaction(function () use ($user, $data): MoneySetting {
            if (array_key_exists('timezone', $data) && is_string($data['timezone']) && $data['timezone'] !== '') {
                $timezone = $this->timezoneResolver->validate($data['timezone']) ?? 'UTC';
                $user->forceFill(['timezone' => $timezone])->save();
            }

            $setting = $this->ensureForUser($user);

            $attributes = [];
            foreach ([
                'minimum_living_budget_minor',
                'safety_buffer_minor',
                'uncertain_outflow_reserve_bps',
                'include_expected_income',
                'calculation_horizon_months',
                'formula_version',
                'currency_code',
            ] as $key) {
                if (array_key_exists($key, $data)) {
                    $attributes[$key] = $data[$key];
                }
            }

            if ($attributes !== []) {
                $setting->fill($attributes);
                $setting->save();
            }

            $this->seedDefaultCategories((int) $user->id);

            return $setting->refresh();
        });
    }

    /**
     * @return list<array{
     *     name: string,
     *     direction_scope: MoneyDirectionScope,
     *     flexibility_default: MoneyFlexibility,
     *     cost_behavior_default: MoneyCostBehavior,
     *     is_essential: bool
     * }>
     */
    private function defaultCategoryDefinitions(): array
    {
        $required = ['住居', '税金', '医療'];
        $essential = ['住居', '食費', '日用品', '通信費', '医療', '税金'];
        $fixed = [
            '住居',
            '通信費',
            'サブスクリプション',
            'AI・開発ツール',
            '税金',
            'ローン返済',
            'クレジットカード返済',
            '後払い返済',
            'ジム・スポーツ',
            '習い事',
            '給与',
        ];

        $expenseNames = [
            '住居',
            '食費',
            '日用品',
            '交通費',
            '高速道路',
            'ガソリン',
            '通信費',
            '医療',
            '美容',
            'ジム・スポーツ',
            '習い事',
            'トレーニング用品',
            'サブスクリプション',
            'AI・開発ツール',
            '税金',
            'ローン返済',
            'クレジットカード返済',
            '後払い返済',
            '娯楽',
            '交際費',
            'その他',
        ];

        $incomeNames = [
            '給与',
            '副業',
            '臨時収入',
            '返金',
            'その他収入',
        ];

        $definitions = [];

        foreach ($expenseNames as $name) {
            $definitions[] = [
                'name' => $name,
                'direction_scope' => MoneyDirectionScope::Expense,
                'flexibility_default' => in_array($name, $required, true)
                    ? MoneyFlexibility::Required
                    : MoneyFlexibility::Adjustable,
                'cost_behavior_default' => in_array($name, $fixed, true)
                    ? MoneyCostBehavior::Fixed
                    : MoneyCostBehavior::Variable,
                'is_essential' => in_array($name, $essential, true),
            ];
        }

        foreach ($incomeNames as $name) {
            $definitions[] = [
                'name' => $name,
                'direction_scope' => MoneyDirectionScope::Income,
                'flexibility_default' => MoneyFlexibility::Adjustable,
                'cost_behavior_default' => in_array($name, $fixed, true)
                    ? MoneyCostBehavior::Fixed
                    : MoneyCostBehavior::Variable,
                'is_essential' => false,
            ];
        }

        return $definitions;
    }
}
