<?php

namespace App\Domain\Yoyu\Models;

use App\Domain\Shared\Models\BelongsToUser;
use App\Domain\Yoyu\Support\PlaceNameNormalizer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property string $normalized_name
 * @property int $travel_minutes
 */
#[Fillable(['user_id', 'name', 'normalized_name', 'travel_minutes'])]
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

    protected static function booted(): void
    {
        static::saving(function (YoyuPlace $place): void {
            $place->normalized_name = PlaceNameNormalizer::normalize((string) $place->name);
        });
    }

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
