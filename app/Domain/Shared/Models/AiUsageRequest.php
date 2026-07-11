<?php

namespace App\Domain\Shared\Models;

use App\Enums\AiUsageRequestStatus;
use Database\Factories\Domain\Shared\AiUsageRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $period
 * @property string $feature
 * @property string $model
 * @property string $estimated_usd
 * @property string|null $actual_usd
 * @property string|null $charged_usd
 * @property AiUsageRequestStatus $status
 * @property Carbon|null $provider_started_at
 * @property Carbon|null $finished_at
 * @property string|null $failure_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'period',
    'feature',
    'model',
    'estimated_usd',
    'actual_usd',
    'charged_usd',
    'status',
    'provider_started_at',
    'finished_at',
    'failure_code',
])]
class AiUsageRequest extends Model
{
    /** @use HasFactory<AiUsageRequestFactory> */
    use BelongsToUser, HasFactory, HasUlids;

    protected $table = 'ai_usage_requests';

    protected static function newFactory(): AiUsageRequestFactory
    {
        return AiUsageRequestFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estimated_usd' => 'decimal:6',
            'actual_usd' => 'decimal:6',
            'charged_usd' => 'decimal:6',
            'status' => AiUsageRequestStatus::class,
            'provider_started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return HasOne<AiUsageLog, $this>
     */
    public function usageLog(): HasOne
    {
        return $this->hasOne(AiUsageLog::class, 'usage_request_id');
    }
}
