<?php

namespace App\Models;

use App\Enums\GoalMetricDirection;
use Database\Factories\GoalMetricFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $goal_id
 * @property string $metric_id
 * @property string|null $baseline_value
 * @property string|null $target_value
 * @property string|null $target_low
 * @property string|null $target_high
 * @property GoalMetricDirection|null $direction
 * @property string|null $note
 * @property int $sort_order
 */
#[Fillable([
    'goal_id',
    'metric_id',
    'baseline_value',
    'target_value',
    'target_low',
    'target_high',
    'direction',
    'note',
    'sort_order',
])]
class GoalMetric extends Model
{
    /** @use HasFactory<GoalMetricFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => GoalMetricDirection::class,
        ];
    }

    /**
     * @return BelongsTo<Goal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /**
     * @return BelongsTo<Metric, $this>
     */
    public function metric(): BelongsTo
    {
        return $this->belongsTo(Metric::class);
    }
}
