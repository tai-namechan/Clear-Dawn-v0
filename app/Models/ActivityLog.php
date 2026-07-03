<?php

namespace App\Models;

use App\Enums\ActivityLogEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * 不変のイベントログ。作成後に更新・削除しない（docs/data/tables.md）。
 *
 * @property string $id
 * @property int $user_id
 * @property ActivityLogEventType $event_type
 * @property string $subject_type
 * @property string $subject_id
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'event_type', 'subject_type', 'subject_id', 'occurred_at'])]
class ActivityLog extends Model
{
    use HasUlids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => ActivityLogEventType::class,
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
