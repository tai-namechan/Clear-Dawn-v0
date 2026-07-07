<?php

namespace App\Models;

use Database\Factories\MetricRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $metric_id
 * @property string|null $life_area_id
 * @property Carbon $recorded_on
 * @property string $value
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'metric_id',
    'life_area_id',
    'recorded_on',
    'value',
    'note',
])]
class MetricRecord extends Model
{
    /** @use HasFactory<MetricRecordFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recorded_on' => 'date',
            'value' => 'decimal:2',
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
     * @return BelongsTo<Metric, $this>
     */
    public function metric(): BelongsTo
    {
        return $this->belongsTo(Metric::class);
    }

    /**
     * @return BelongsTo<LifeArea, $this>
     */
    public function lifeArea(): BelongsTo
    {
        return $this->belongsTo(LifeArea::class);
    }
}
