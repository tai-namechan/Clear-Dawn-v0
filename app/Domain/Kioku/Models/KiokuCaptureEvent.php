<?php

namespace App\Domain\Kioku\Models;

use App\Domain\Shared\Models\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Capture funnel metric event. Never stores raw content, transcripts,
 * or audio (docs/product/kioku-quick-capture.md §13).
 *
 * @property string $id
 * @property int $user_id
 * @property string $event
 * @property string $source_type
 * @property int|null $duration_ms
 * @property int|null $retry_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'event',
    'source_type',
    'duration_ms',
    'retry_count',
])]
class KiokuCaptureEvent extends Model
{
    use BelongsToUser, HasUlids;

    public const EVENTS = [
        'capture_started',
        'local_saved',
        'local_save_failed',
        'server_synced',
        'sync_failed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_ms' => 'integer',
            'retry_count' => 'integer',
        ];
    }
}
