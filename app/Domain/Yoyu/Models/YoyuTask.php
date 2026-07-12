<?php

namespace App\Domain\Yoyu\Models;

use App\Domain\Shared\Models\BelongsToUser;
use Database\Factories\Domain\Yoyu\YoyuTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $title
 * @property string $status
 * @property int $estimate_minutes
 * @property Carbon|null $due_date
 * @property Carbon|null $planned_date
 * @property string|null $priority
 * @property string|null $category
 */
#[Fillable([
    'user_id',
    'title',
    'status',
    'estimate_minutes',
    'due_date',
    'planned_date',
    'priority',
    'category',
])]
class YoyuTask extends Model
{
    /** @use HasFactory<YoyuTaskFactory> */
    use BelongsToUser, HasFactory, HasUlids;

    protected $table = 'yoyu_tasks';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'planned',
        'estimate_minutes' => 30,
    ];

    protected static function newFactory(): YoyuTaskFactory
    {
        return YoyuTaskFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estimate_minutes' => 'integer',
            'due_date' => 'date',
            'planned_date' => 'date',
        ];
    }
}
