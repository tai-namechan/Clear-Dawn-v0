<?php

namespace App\Models;

use App\Enums\RecommendationScope;
use App\Enums\RecommendationStatus;
use Database\Factories\RecommendationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string|null $rule_evaluation_id
 * @property Carbon $recommended_on
 * @property RecommendationScope $scope
 * @property string $title
 * @property string|null $rationale
 * @property string|null $goal_impact
 * @property array<string, mixed>|null $plan_diff
 * @property string|null $confidence
 * @property array<string, mixed>|null $missing_data
 * @property bool $is_interrupt
 * @property RecommendationStatus $status
 */
#[Fillable([
    'user_id',
    'rule_evaluation_id',
    'recommended_on',
    'scope',
    'title',
    'rationale',
    'goal_impact',
    'plan_diff',
    'confidence',
    'missing_data',
    'is_interrupt',
    'status',
])]
class Recommendation extends Model
{
    /** @use HasFactory<RecommendationFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recommended_on' => 'date',
            'scope' => RecommendationScope::class,
            'plan_diff' => 'array',
            'confidence' => 'decimal:2',
            'missing_data' => 'array',
            'is_interrupt' => 'boolean',
            'status' => RecommendationStatus::class,
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
     * @return BelongsTo<RuleEvaluation, $this>
     */
    public function ruleEvaluation(): BelongsTo
    {
        return $this->belongsTo(RuleEvaluation::class);
    }

    /**
     * @return HasMany<RecommendationOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(RecommendationOption::class)->orderBy('sort_order');
    }

    /**
     * @return HasOne<RecommendationDecision, $this>
     */
    public function decision(): HasOne
    {
        return $this->hasOne(RecommendationDecision::class);
    }
}
