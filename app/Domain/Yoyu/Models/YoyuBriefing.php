<?php

namespace App\Domain\Yoyu\Models;

use App\Domain\Shared\Models\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property Carbon $date
 * @property string $body
 * @property string $status
 */
#[Fillable(['user_id', 'date', 'body', 'status'])]
class YoyuBriefing extends Model
{
    use BelongsToUser, HasUlids;

    protected $table = 'yoyu_briefings';

    protected $attributes = [
        'status' => 'ready',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
