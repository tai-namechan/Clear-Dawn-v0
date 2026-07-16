<?php

namespace App\Models;

use App\Enums\GoalStatus;
use Database\Factories\GoalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string|null $parent_goal_id
 * @property string|null $matrix_cell_id
 * @property string $name
 * @property string|null $why
 * @property int $priority
 * @property GoalStatus $status
 * @property Carbon|null $deadline
 * @property int $sort_order
 */
#[Fillable([
    'user_id',
    'parent_goal_id',
    'matrix_cell_id',
    'name',
    'why',
    'priority',
    'status',
    'deadline',
    'sort_order',
])]
class Goal extends Model
{
    /** @use HasFactory<GoalFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'status' => GoalStatus::class,
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
     * @return BelongsTo<Goal, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_goal_id');
    }

    /**
     * @return HasMany<Goal, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_goal_id')->orderBy('sort_order');
    }

    /**
     * @return BelongsTo<MatrixCell, $this>
     */
    public function matrixCell(): BelongsTo
    {
        return $this->belongsTo(MatrixCell::class);
    }

    /**
     * @return HasMany<GoalMetric, $this>
     */
    public function goalMetrics(): HasMany
    {
        return $this->hasMany(GoalMetric::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<GoalChangeLog, $this>
     */
    public function changeLogs(): HasMany
    {
        return $this->hasMany(GoalChangeLog::class)->orderByDesc('created_at');
    }

    /**
     * @return HasMany<Program, $this>
     */
    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }
}
