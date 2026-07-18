<?php

namespace App\Models;

use Database\Factories\DailyResourceStateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property Carbon $state_on
 * @property string $resource_key
 * @property string|null $ewma
 * @property string|null $z_load
 * @property string|null $rel_strain
 * @property string|null $readiness
 * @property array<string, mixed>|null $inputs_snapshot
 */
#[Fillable([
    'user_id',
    'state_on',
    'resource_key',
    'ewma',
    'z_load',
    'rel_strain',
    'readiness',
    'inputs_snapshot',
])]
class DailyResourceState extends Model
{
    /** @use HasFactory<DailyResourceStateFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state_on' => 'date',
            'ewma' => 'decimal:4',
            'z_load' => 'decimal:4',
            'rel_strain' => 'decimal:4',
            'readiness' => 'decimal:4',
            'inputs_snapshot' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
