<?php

namespace App\Domain\Yoyu\Models;

use App\Domain\Shared\Models\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property int $user_id
 * @property int $prep_minutes
 * @property int $buffer_minutes
 */
#[Fillable(['user_id', 'prep_minutes', 'buffer_minutes'])]
class YoyuPreference extends Model
{
    use BelongsToUser, HasUlids;

    protected $table = 'yoyu_preferences';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'prep_minutes' => 10,
        'buffer_minutes' => 5,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prep_minutes' => 'integer',
            'buffer_minutes' => 'integer',
        ];
    }
}
