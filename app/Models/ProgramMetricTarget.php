<?php

namespace App\Models;

use Database\Factories\ProgramMetricTargetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $program_version_id
 * @property string $metric_id
 * @property string|null $target_value
 * @property string|null $target_low
 * @property string|null $target_high
 * @property string|null $note
 */
#[Fillable([
    'program_version_id',
    'metric_id',
    'target_value',
    'target_low',
    'target_high',
    'note',
])]
class ProgramMetricTarget extends Model
{
    /** @use HasFactory<ProgramMetricTargetFactory> */
    use HasFactory, HasUlids;

    /**
     * @return BelongsTo<ProgramVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ProgramVersion::class, 'program_version_id');
    }

    /**
     * @return BelongsTo<Metric, $this>
     */
    public function metric(): BelongsTo
    {
        return $this->belongsTo(Metric::class);
    }
}
