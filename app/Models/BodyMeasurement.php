<?php

namespace App\Models;

use App\Enums\BodyMeasurementSource;
use App\Enums\BodyMeasurementStatus;
use Database\Factories\BodyMeasurementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * 体組成測定レコード。
 *
 * 部位別値の正本は segments。画像用に数値を複製しない。
 *
 * @property string $id
 * @property int $user_id
 * @property Carbon $measured_on
 * @property BodyMeasurementSource $input_source
 * @property string|null $source_pdf_path
 * @property BodyMeasurementStatus $parse_status
 * @property Carbon|null $confirmed_at
 * @property string|null $weight_kg
 * @property string|null $lean_body_mass_kg
 * @property string|null $skeletal_muscle_mass_kg
 * @property string|null $body_fat_percentage
 * @property string|null $abdominal_circumference_cm
 * @property array<string, mixed>|null $raw_extracted_json
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'measured_on',
    'input_source',
    'source_pdf_path',
    'parse_status',
    'confirmed_at',
    'weight_kg',
    'lean_body_mass_kg',
    'skeletal_muscle_mass_kg',
    'body_fat_percentage',
    'abdominal_circumference_cm',
    'raw_extracted_json',
])]
class BodyMeasurement extends Model
{
    /** @use HasFactory<BodyMeasurementFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'measured_on' => 'date',
            'input_source' => BodyMeasurementSource::class,
            'parse_status' => BodyMeasurementStatus::class,
            'confirmed_at' => 'datetime',
            'weight_kg' => 'decimal:2',
            'lean_body_mass_kg' => 'decimal:2',
            'skeletal_muscle_mass_kg' => 'decimal:2',
            'body_fat_percentage' => 'decimal:2',
            'abdominal_circumference_cm' => 'decimal:2',
            'raw_extracted_json' => 'array',
        ];
    }

    /**
     * 所有者。
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 部位別の除脂肪量・脂肪量。
     *
     * @return HasMany<BodyMeasurementSegment, $this>
     */
    public function segments(): HasMany
    {
        return $this->hasMany(BodyMeasurementSegment::class);
    }
}
