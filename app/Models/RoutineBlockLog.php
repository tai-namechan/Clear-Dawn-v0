<?php

namespace App\Models;

use Database\Factories\RoutineBlockLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $routine_session_step_id
 * @property int $block_number
 * @property string|null $load_value
 * @property string|null $load_unit
 * @property string|null $amount_value
 * @property string|null $amount_unit
 * @property string|null $memo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'routine_session_step_id',
    'block_number',
    'load_value',
    'load_unit',
    'amount_value',
    'amount_unit',
    'memo',
])]
class RoutineBlockLog extends Model
{
    /** @use HasFactory<RoutineBlockLogFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'load_value' => 'decimal:2',
            'amount_value' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<RoutineSessionStep, $this>
     */
    public function routineSessionStep(): BelongsTo
    {
        return $this->belongsTo(RoutineSessionStep::class);
    }
}
