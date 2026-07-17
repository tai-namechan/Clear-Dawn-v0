<?php

namespace App\Models;

use Database\Factories\MeasurementSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int|null $user_id
 * @property string $key
 * @property string $label
 * @property string|null $description
 */
#[Fillable([
    'user_id',
    'key',
    'label',
    'description',
])]
class MeasurementSource extends Model
{
    /** @use HasFactory<MeasurementSourceFactory> */
    use HasFactory, HasUlids;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
