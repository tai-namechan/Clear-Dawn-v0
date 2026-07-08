<?php

namespace App\Models;

use App\Enums\RoutineSessionStatus;
use Database\Factories\RoutineSessionFactory;
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
 * @property string $routine_plan_id
 * @property RoutineSessionStatus $status
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'routine_plan_id',
    'status',
    'started_at',
    'finished_at',
    'note',
])]
class RoutineSession extends Model
{
    /** @use HasFactory<RoutineSessionFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RoutineSessionStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
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
     * @return BelongsTo<RoutinePlan, $this>
     */
    public function routinePlan(): BelongsTo
    {
        return $this->belongsTo(RoutinePlan::class);
    }

    /**
     * @return HasMany<RoutineSessionStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(RoutineSessionStep::class)->orderBy('sort_order');
    }
}
