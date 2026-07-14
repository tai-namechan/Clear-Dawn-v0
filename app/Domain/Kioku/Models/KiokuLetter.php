<?php

namespace App\Domain\Kioku\Models;

use App\Domain\Shared\Models\BelongsToUser;
use Database\Factories\Domain\Kioku\KiokuLetterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Weekly concierge letter (docs/product/kioku-final-remaining-
 * implementation.md §10–11). One letter per user per week;
 * character_variant is fixed at creation and only changes presentation
 * (image / CSS theme / signature), never candidates or the AI body.
 *
 * @property string $id
 * @property int $user_id
 * @property Carbon $week_start
 * @property string $status
 * @property string $character_variant
 * @property string|null $intro
 * @property string|null $context
 * @property int $candidate_count
 * @property int $item_count
 * @property string $prompt_key
 * @property string|null $model
 * @property array<string, mixed>|null $generation_meta
 * @property Carbon|null $generated_at
 * @property Carbon|null $published_at
 * @property Carbon|null $opened_at
 * @property Carbon|null $completed_at
 * @property string|null $evaluation_memory_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'week_start',
    'status',
    'character_variant',
    'intro',
    'context',
    'candidate_count',
    'item_count',
    'prompt_key',
    'model',
    'generation_meta',
    'generated_at',
    'published_at',
    'opened_at',
    'completed_at',
    'evaluation_memory_id',
])]
class KiokuLetter extends Model
{
    /** @use HasFactory<KiokuLetterFactory> */
    use BelongsToUser, HasFactory, HasUlids;

    public const STATUS_GENERATING = 'generating';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_EMPTY = 'empty';

    public const STATUS_FAILED = 'failed';

    public const STATUS_OPENED = 'opened';

    public const STATUS_EVALUATING = 'evaluating';

    public const STATUS_EVALUATED = 'evaluated';

    public const STATUS_HALTED = 'halted';

    public const CHARACTER_VARIANTS = ['shiori', 'nagi'];

    protected static function newFactory(): KiokuLetterFactory
    {
        return KiokuLetterFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'candidate_count' => 'integer',
            'item_count' => 'integer',
            'generation_meta' => 'array',
            'generated_at' => 'datetime',
            'published_at' => 'datetime',
            'opened_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<KiokuLetterItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(KiokuLetterItem::class, 'letter_id')->orderBy('position');
    }

    /**
     * @return BelongsTo<Memory, $this>
     */
    public function evaluationMemory(): BelongsTo
    {
        return $this->belongsTo(Memory::class, 'evaluation_memory_id');
    }

    /**
     * Verdicts are frozen once the evaluation memory has been written.
     */
    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }
}
