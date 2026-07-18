<?php

namespace App\Models;

use App\Enums\MetricValueType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 指標マスタ。グローバル行は user_id = null（timestamps を持たない）。
 * ユーザー定義指標（専門測定値等）は user_id 付きで追加できる。
 *
 * @property string $id
 * @property int|null $user_id
 * @property string $key
 * @property string $label
 * @property string $unit
 * @property MetricValueType $value_type
 * @property int $sort_order
 * @property string|null $description_plain
 * @property string|null $measurement_method
 * @property bool $is_advanced 専門測定値（既定で非表示）
 */
#[Fillable([
    'user_id',
    'key',
    'label',
    'unit',
    'value_type',
    'sort_order',
    'description_plain',
    'measurement_method',
    'is_advanced',
])]
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
            'is_advanced' => 'boolean',
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
