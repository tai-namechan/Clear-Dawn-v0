<?php

namespace App\Models;

use App\Enums\MetricValueType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * グローバルマスタ（user_id / timestamps を持たない）。
 *
 * @property string $id
 * @property string $key
 * @property string $label
 * @property string $unit
 * @property MetricValueType $value_type
 * @property int $sort_order
 */
#[Fillable(['key', 'label', 'unit', 'value_type', 'sort_order'])]
class Metric extends Model
{
    use HasUlids;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value_type' => MetricValueType::class,
        ];
    }

    /**
     * @return HasMany<MetricRecord, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(MetricRecord::class);
    }
}
