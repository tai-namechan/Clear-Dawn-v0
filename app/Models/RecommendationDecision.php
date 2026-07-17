<?php

namespace App\Models;

use Database\Factories\RecommendationDecisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property int $user_id
 * @property string $recommendation_id
 * @property string|null $recommendation_option_id
 * @property string $action_key
 * @property string|null $reason
 * @property array<string, mixed>|null $result_snapshot
 */
#[Fillable([
    'user_id',
    'recommendation_id',
    'recommendation_option_id',
    'action_key',
    'reason',
    'result_snapshot',
])]
class RecommendationDecision extends Model
{
    /** @use HasFactory<RecommendationDecisionFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result_snapshot' => 'array',
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
     * @return BelongsTo<Recommendation, $this>
     */
    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(Recommendation::class);
    }

    /**
     * @return BelongsTo<RecommendationOption, $this>
     */
    public function recommendationOption(): BelongsTo
    {
        return $this->belongsTo(RecommendationOption::class);
    }

    /**
     * @return HasMany<OutcomeEvaluation, $this>
     */
    public function outcomeEvaluations(): HasMany
    {
        return $this->hasMany(OutcomeEvaluation::class);
    }
}
