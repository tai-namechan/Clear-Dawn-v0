<?php

namespace App\Models;

use App\Enums\RoutineSessionStepStatus;
use App\Enums\StepPurpose;
use Database\Factories\RoutineSessionStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $routine_session_id
 * @property string|null $routine_item_id
 * @property string $item_name
 * @property string|null $video_id
 * @property StepPurpose|null $purpose
 * @property int $sort_order
 * @property string|null $target_load
 * @property string|null $load_unit
 * @property string|null $target_amount
 * @property string|null $amount_unit
 * @property int|null $target_blocks
 * @property int|null $rest_seconds
 * @property RoutineSessionStepStatus $status
 * @property int|null $actual_duration_seconds
 * @property string|null $memo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'routine_session_id',
    'routine_item_id',
    'item_name',
    'video_id',
    'purpose',
    'sort_order',
    'target_load',
    'load_unit',
    'target_amount',
    'amount_unit',
    'target_blocks',
    'rest_seconds',
    'status',
    'actual_duration_seconds',
    'memo',
    'status_reason',
    'pain_score',
    'pain_location',
])]
class RoutineSessionStep extends Model
{
    /** @use HasFactory<RoutineSessionStepFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => StepPurpose::class,
            'status' => RoutineSessionStepStatus::class,
            'target_load' => 'decimal:2',
            'target_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<RoutineSession, $this>
     */
    public function routineSession(): BelongsTo
    {
        return $this->belongsTo(RoutineSession::class);
    }

    /**
     * @return BelongsTo<RoutineItem, $this>
     */
    public function routineItem(): BelongsTo
    {
        return $this->belongsTo(RoutineItem::class);
    }

    /**
     * @return BelongsTo<Video, $this>
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /**
     * @return HasMany<RoutineBlockLog, $this>
     */
    public function blockLogs(): HasMany
    {
        return $this->hasMany(RoutineBlockLog::class)->orderBy('block_number');
    }
}
