<?php

namespace App\Models;

use App\Enums\RuleDefinitionKind;
use Database\Factories\RuleDefinitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property int|null $user_id
 * @property string $key
 * @property RuleDefinitionKind $kind
 * @property string $title
 * @property string|null $description
 * @property array<string, mixed>|null $params
 * @property string|null $evidence
 * @property string|null $population
 * @property string|null $limitations
 * @property string|null $confidence
 * @property string|null $verified_by
 * @property int $version_number
 * @property bool $is_active
 * @property bool $is_hard_gate
 */
#[Fillable([
    'user_id',
    'key',
    'kind',
    'title',
    'description',
    'params',
    'evidence',
    'population',
    'limitations',
    'confidence',
    'verified_by',
    'version_number',
    'is_active',
    'is_hard_gate',
])]
class RuleDefinition extends Model
{
    /** @use HasFactory<RuleDefinitionFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => RuleDefinitionKind::class,
            'params' => 'array',
            'confidence' => 'decimal:2',
            'is_active' => 'boolean',
            'is_hard_gate' => 'boolean',
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
     * @return HasMany<RuleEvaluation, $this>
     */
    public function ruleEvaluations(): HasMany
    {
        return $this->hasMany(RuleEvaluation::class);
    }
}
