<?php

namespace App\Models;

use Database\Factories\ProgramConstraintFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * プログラム固有の制約（program_rule）。配置原則・投球上限・削減優先順位など。
 *
 * @property string $id
 * @property string $program_version_id
 * @property string $key
 * @property string $kind
 * @property string $description
 * @property array<string, mixed>|null $params
 * @property int $sort_order
 */
#[Fillable([
    'program_version_id',
    'key',
    'kind',
    'description',
    'params',
    'sort_order',
])]
class ProgramConstraint extends Model
{
    /** @use HasFactory<ProgramConstraintFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'params' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ProgramVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ProgramVersion::class, 'program_version_id');
    }
}
