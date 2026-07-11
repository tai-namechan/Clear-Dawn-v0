<?php

namespace App\Domain\Shared\Models;

use Database\Factories\Domain\Shared\AiUsageMonthlyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $period
 * @property string $spent_usd
 * @property string $reserved_usd
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'period',
    'spent_usd',
    'reserved_usd',
])]
class AiUsageMonthly extends Model
{
    /** @use HasFactory<AiUsageMonthlyFactory> */
    use BelongsToUser, HasFactory, HasUlids;

    protected $table = 'ai_usage_monthly';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'spent_usd' => '0.000000',
        'reserved_usd' => '0.000000',
    ];

    protected static function newFactory(): AiUsageMonthlyFactory
    {
        return AiUsageMonthlyFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'spent_usd' => 'decimal:6',
            'reserved_usd' => 'decimal:6',
        ];
    }
}
