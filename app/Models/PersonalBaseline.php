<?php

namespace App\Models;

use Database\Factories\PersonalBaselineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $resource_key
 * @property string|null $mean_value
 * @property string|null $stddev_value
 * @property int $sample_count
 * @property Carbon|null $window_start
 * @property Carbon|null $window_end
 * @property Carbon|null $computed_at
 */
#[Fillable([
    'user_id',
    'resource_key',
    'mean_value',
    'stddev_value',
    'sample_count',
    'window_start',
    'window_end',
    'computed_at',
])]
class PersonalBaseline extends Model
{
    /** @use HasFactory<PersonalBaselineFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mean_value' => 'decimal:4',
            'stddev_value' => 'decimal:4',
            'window_start' => 'date',
            'window_end' => 'date',
            'computed_at' => 'datetime',
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
