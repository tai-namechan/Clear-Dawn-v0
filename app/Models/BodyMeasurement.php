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
 * @property Carbon|null $measured_at
 * @property BodyMeasurementSource $input_source
 * @property string|null $source_pdf_path
 * @property BodyMeasurementStatus $parse_status
 * @property Carbon|null $confirmed_at
 * @property string|null $height_cm
 * @property int|null $age
 * @property string|null $gender
 * @property string|null $weight_kg
 * @property string|null $lean_body_mass_kg
 * @property string|null $skeletal_muscle_mass_kg
 * @property string|null $protein_kg
 * @property string|null $mineral_kg
 * @property string|null $total_body_water_kg
 * @property string|null $body_fat_mass_kg
 * @property string|null $subcutaneous_fat_mass_kg
 * @property string|null $visceral_fat_mass_kg
 * @property string|null $visceral_fat_area_cm2
 * @property int|null $visceral_fat_level
 * @property string|null $body_fat_percentage
 * @property int|null $bmr_kcal
 * @property int|null $tee_kcal
 * @property int|null $bio_age
 * @property string|null $bwi_score
 * @property string|null $abdominal_circumference_cm
 * @property string|null $waist_to_hip_ratio
 * @property int|null $recommended_calories_min
 * @property int|null $recommended_calories_max
 * @property string|null $recommended_protein_min_g
 * @property string|null $recommended_protein_max_g
 * @property string|null $recommended_carbohydrate_min_g
 * @property string|null $recommended_carbohydrate_max_g
 * @property string|null $recommended_fat_min_g
 * @property string|null $recommended_fat_max_g
 * @property array<string, mixed>|null $raw_extracted_json
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'measured_on',
    'measured_at',
    'input_source',
    'source_pdf_path',
    'parse_status',
    'confirmed_at',
    'height_cm',
    'age',
    'gender',
    'weight_kg',
    'lean_body_mass_kg',
    'skeletal_muscle_mass_kg',
    'protein_kg',
    'mineral_kg',
    'total_body_water_kg',
    'body_fat_mass_kg',
    'subcutaneous_fat_mass_kg',
    'visceral_fat_mass_kg',
    'visceral_fat_area_cm2',
    'visceral_fat_level',
    'body_fat_percentage',
    'bmr_kcal',
    'tee_kcal',
    'bio_age',
    'bwi_score',
    'abdominal_circumference_cm',
    'waist_to_hip_ratio',
    'recommended_calories_min',
    'recommended_calories_max',
    'recommended_protein_min_g',
    'recommended_protein_max_g',
    'recommended_carbohydrate_min_g',
    'recommended_carbohydrate_max_g',
    'recommended_fat_min_g',
    'recommended_fat_max_g',
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
            'measured_at' => 'datetime',
            'input_source' => BodyMeasurementSource::class,
            'parse_status' => BodyMeasurementStatus::class,
            'confirmed_at' => 'datetime',
            'height_cm' => 'decimal:1',
            'age' => 'integer',
            'weight_kg' => 'decimal:2',
            'lean_body_mass_kg' => 'decimal:2',
            'skeletal_muscle_mass_kg' => 'decimal:2',
            'protein_kg' => 'decimal:2',
            'mineral_kg' => 'decimal:2',
            'total_body_water_kg' => 'decimal:2',
            'body_fat_mass_kg' => 'decimal:2',
            'subcutaneous_fat_mass_kg' => 'decimal:2',
            'visceral_fat_mass_kg' => 'decimal:2',
            'visceral_fat_area_cm2' => 'decimal:2',
            'visceral_fat_level' => 'integer',
            'body_fat_percentage' => 'decimal:2',
            'bmr_kcal' => 'integer',
            'tee_kcal' => 'integer',
            'bio_age' => 'integer',
            'bwi_score' => 'decimal:1',
            'abdominal_circumference_cm' => 'decimal:2',
            'waist_to_hip_ratio' => 'decimal:3',
            'recommended_calories_min' => 'integer',
            'recommended_calories_max' => 'integer',
            'recommended_protein_min_g' => 'decimal:1',
            'recommended_protein_max_g' => 'decimal:1',
            'recommended_carbohydrate_min_g' => 'decimal:1',
            'recommended_carbohydrate_max_g' => 'decimal:1',
            'recommended_fat_min_g' => 'decimal:1',
            'recommended_fat_max_g' => 'decimal:1',
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
