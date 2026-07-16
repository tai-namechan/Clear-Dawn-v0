<?php

namespace App\Models;

use Database\Factories\ProgramChoiceOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $program_choice_group_id
 * @property string $label
 * @property string|null $description
 * @property int|null $estimated_minutes
 * @property int $sort_order
 */
#[Fillable([
    'program_choice_group_id',
    'label',
    'description',
    'estimated_minutes',
    'sort_order',
])]
class ProgramChoiceOption extends Model
{
    /** @use HasFactory<ProgramChoiceOptionFactory> */
    use HasFactory, HasUlids;

    /**
     * @return BelongsTo<ProgramChoiceGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(ProgramChoiceGroup::class, 'program_choice_group_id');
    }

    /**
     * このオプションを選んだときに有効になる STEP 群。
     *
     * @return HasMany<ProgramDayStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(ProgramDayStep::class, 'program_choice_option_id')->orderBy('sort_order');
    }
}
