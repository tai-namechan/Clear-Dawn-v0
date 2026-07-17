<?php

namespace App\Domain\Yoyu\Money\Models;

use App\Domain\Shared\Models\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Unique money settings row per user. */
#[Fillable([
    'user_id',
    'currency_code',
    'minimum_living_budget_minor',
    'safety_buffer_minor',
    'uncertain_outflow_reserve_bps',
    'include_expected_income',
    'calculation_horizon_months',
    'formula_version',
])]
class MoneySetting extends Model
{
    use BelongsToUser, HasUlids;

    protected $table = 'yoyu_money_settings';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency_code' => 'JPY',
        'uncertain_outflow_reserve_bps' => 10000,
        'include_expected_income' => false,
        'calculation_horizon_months' => 3,
        'formula_version' => '1',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minimum_living_budget_minor' => 'integer',
            'safety_buffer_minor' => 'integer',
            'uncertain_outflow_reserve_bps' => 'integer',
            'include_expected_income' => 'boolean',
            'calculation_horizon_months' => 'integer',
        ];
    }
}
