<?php

namespace App\Domain\Yoyu\Models;

use App\Domain\Kioku\Models\Memory;
use App\Domain\Shared\Models\BelongsToUser;
use Database\Factories\Domain\Yoyu\YoyuFocusItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $memory_id
 * @property string $status
 * @property Carbon|null $snoozed_until
 * @property string|null $converted_task_id
 */
#[Fillable([
    'user_id',
    'memory_id',
    'status',
    'snoozed_until',
    'converted_task_id',
])]
class YoyuFocusItem extends Model
{
    /** @use HasFactory<YoyuFocusItemFactory> */
    use BelongsToUser, HasFactory, HasUlids;

    protected $table = 'yoyu_focus_items';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
    ];

    protected static function newFactory(): YoyuFocusItemFactory
    {
        return YoyuFocusItemFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snoozed_until' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Memory, $this>
     */
    public function memory(): BelongsTo
    {
        return $this->belongsTo(Memory::class);
    }

    /**
     * @return BelongsTo<YoyuTask, $this>
     */
    public function convertedTask(): BelongsTo
    {
        return $this->belongsTo(YoyuTask::class, 'converted_task_id');
    }
}
