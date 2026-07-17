<?php

namespace App\Models;

use Database\Factories\OutcomeEvaluationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string|null $recommendation_decision_id
 * @property string|null $routine_session_id
 * @property Carbon $evaluated_on
 * @property string $outcome_key
 * @property string|null $score
 * @property string|null $note
 * @property array<string, mixed>|null $metrics_snapshot
 */
#[Fillable([
    'user_id',
    'recommendation_decision_id',
    'routine_session_id',
    'evaluated_on',
    'outcome_key',
    'score',
    'note',
    'metrics_snapshot',
])]
class OutcomeEvaluation extends Model
{
    /** @use HasFactory<OutcomeEvaluationFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'evaluated_on' => 'date',
            'score' => 'decimal:2',
            'metrics_snapshot' => 'array',
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
     * @return BelongsTo<RecommendationDecision, $this>
     */
    public function recommendationDecision(): BelongsTo
    {
        return $this->belongsTo(RecommendationDecision::class);
    }

    /**
     * @return BelongsTo<RoutineSession, $this>
     */
    public function routineSession(): BelongsTo
    {
        return $this->belongsTo(RoutineSession::class);
    }
}
