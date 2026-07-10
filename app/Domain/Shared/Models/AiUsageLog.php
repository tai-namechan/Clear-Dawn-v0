<?php

namespace App\Domain\Shared\Models;

use Database\Factories\Domain\Shared\AiUsageLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $feature
 * @property string $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property string $estimated_cost_usd
 * @property Carbon|null $created_at
 */
#[Fillable([
    'user_id',
    'feature',
    'model',
    'input_tokens',
    'output_tokens',
    'estimated_cost_usd',
    'created_at',
])]
class AiUsageLog extends Model
{
    /** @use HasFactory<AiUsageLogFactory> */
    use BelongsToUser, HasFactory, HasUlids;

    public $timestamps = false;

    protected static function newFactory(): AiUsageLogFactory
    {
        return AiUsageLogFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'estimated_cost_usd' => 'decimal:4',
            'created_at' => 'datetime',
        ];
    }
}
