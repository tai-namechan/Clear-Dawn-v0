<?php

namespace App\Domain\Yoyu\Money\Models;

use App\Domain\Shared\Models\BelongsToUser;
use App\Domain\Yoyu\Money\Enums\MoneySimulationActionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'simulation_id',
    'action_type',
    'sort_order',
    'target_type',
    'target_id',
    'params_payload',
    'effect_payload',
])]
class MoneySimulationAction extends Model
{
    use BelongsToUser, HasUlids;

    protected $table = 'yoyu_money_simulation_actions';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action_type' => MoneySimulationActionType::class,
            'sort_order' => 'integer',
            'params_payload' => 'array',
            'effect_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<MoneySimulation, $this>
     */
    public function simulation(): BelongsTo
    {
        return $this->belongsTo(MoneySimulation::class);
    }
}
