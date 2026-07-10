<?php

namespace App\Domain\Yoyu\Models;

use App\Domain\Shared\Models\BelongsToUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property int $travel_minutes
 */
#[Fillable(['user_id', 'name', 'travel_minutes'])]
class YoyuPlace extends Model
{
    use BelongsToUser, HasUlids;

    protected $table = 'yoyu_places';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'travel_minutes' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'travel_minutes' => 'integer',
        ];
    }
}
