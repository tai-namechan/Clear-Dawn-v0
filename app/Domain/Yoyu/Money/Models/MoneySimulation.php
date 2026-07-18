<?php

namespace App\Domain\Yoyu\Money\Models;

use App\Domain\Shared\Models\BelongsToUser;
use App\Domain\Yoyu\Money\Enums\MoneySimulationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'name',
    'status',
    'base_date',
    'horizon_months',
    'formula_version',
    'currency_code',
    'assumptions_payload',
    'baseline_payload',
    'result_payload',
    'memo',
])]
class MoneySimulation extends Model
{
    use BelongsToUser, HasUlids;

    protected $table = 'yoyu_money_simulations';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'horizon_months' => 3,
        'formula_version' => '1',
        'currency_code' => 'JPY',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MoneySimulationStatus::class,
            'base_date' => 'date',
            'horizon_months' => 'integer',
            'assumptions_payload' => 'array',
            'baseline_payload' => 'array',
            'result_payload' => 'array',
        ];
    }

    /**
     * @return HasMany<MoneySimulationAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(MoneySimulationAction::class, 'simulation_id');
    }

    /**
     * @return HasMany<MoneyDecision, $this>
     */
    public function decisions(): HasMany
    {
        return $this->hasMany(MoneyDecision::class, 'simulation_id');
    }
}
