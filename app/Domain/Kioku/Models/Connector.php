<?php

namespace App\Domain\Kioku\Models;

use App\Domain\Shared\Models\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $source_type
 * @property string $status
 * @property Carbon|null $last_synced_at
 */
#[Fillable(['user_id', 'source_type', 'status', 'last_synced_at'])]
class Connector extends Model
{
    use BelongsToUser, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'idle',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }
}
