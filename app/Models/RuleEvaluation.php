<?php

namespace App\Models;

use Database\Factories\RuleEvaluationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $rule_definition_id
 * @property Carbon $evaluated_on
 * @property bool $triggered
 * @property array<string, mixed>|null $inputs_snapshot
 * @property array<string, mixed>|null $outputs_snapshot
 */
#[Fillable([
    'user_id',
    'rule_definition_id',
    'evaluated_on',
    'triggered',
    'inputs_snapshot',
    'outputs_snapshot',
])]
class RuleEvaluation extends Model
{
    /** @use HasFactory<RuleEvaluationFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'evaluated_on' => 'date',
            'triggered' => 'boolean',
            'inputs_snapshot' => 'array',
            'outputs_snapshot' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<RuleDefinition, $this>
     */
    public function ruleDefinition(): BelongsTo
    {
        return $this->belongsTo(RuleDefinition::class);
    }

    /**
     * @return HasMany<Recommendation, $this>
     */
    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }
}
