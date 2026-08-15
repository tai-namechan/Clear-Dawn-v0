<?php

namespace App\Models;

use App\Enums\BodySegment;
use Database\Factories\BodyMeasurementSegmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 体組成の部位別値。
 *
 * @property string $id
 * @property string $body_measurement_id
 * @property BodySegment $segment_key
 * @property string|null $lean_mass_kg
 * @property string|null $fat_mass_kg
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'body_measurement_id',
    'segment_key',
    'lean_mass_kg',
    'fat_mass_kg',
])]
class BodyMeasurementSegment extends Model
{
    /** @use HasFactory<BodyMeasurementSegmentFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'segment_key' => BodySegment::class,
            'lean_mass_kg' => 'decimal:2',
            'fat_mass_kg' => 'decimal:2',
        ];
    }

    /**
     * 親の体組成測定。
     *
     * @return BelongsTo<BodyMeasurement, $this>
     */
    public function bodyMeasurement(): BelongsTo
    {
        return $this->belongsTo(BodyMeasurement::class);
    }
}
