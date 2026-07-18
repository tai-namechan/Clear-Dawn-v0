<?php

namespace App\Domain\Yoyu\Money\Models;

use App\Domain\Shared\Models\BelongsToUser;
use App\Domain\Yoyu\Money\Enums\MoneyDecisionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'title',
    'decided_on',
    'status',
    'simulation_id',
    'before_payload',
    'expected_effect_payload',
    'actual_effect_payload',
    'memo',
    'reviewed_at',
])]
class MoneyDecision extends Model
{
    use BelongsToUser, HasUlids;

    protected $table = 'yoyu_money_decisions';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decided_on' => 'date',
            'status' => MoneyDecisionStatus::class,
            'before_payload' => 'array',
            'expected_effect_payload' => 'array',
            'actual_effect_payload' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MoneySimulation, $this>
     */
    public function simulation(): BelongsTo
    {
        return $this->belongsTo(MoneySimulation::class);
    }

    /**
     * @return HasMany<MoneyDecisionLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(MoneyDecisionLink::class, 'decision_id');
    }
}
