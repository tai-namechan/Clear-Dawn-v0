<?php

namespace App\Models;

use Database\Factories\SymptomObservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property Carbon $observed_on
 * @property string $body_region
 * @property string $symptom_kind
 * @property int $severity
 * @property bool $is_new
 * @property string|null $note
 */
#[Fillable([
    'user_id',
    'observed_on',
    'body_region',
    'symptom_kind',
    'severity',
    'is_new',
    'note',
])]
class SymptomObservation extends Model
{
    /** @use HasFactory<SymptomObservationFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'observed_on' => 'date',
            'is_new' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
