<?php

namespace App\Domain\Kioku\Models;

use App\Domain\Kioku\KiokuLetterCadence;
use App\Domain\Kioku\KiokuLetterMode;
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
 * Concierge letter (docs/product/kioku-final-remaining-implementation.md
 * §10–11 + docs/product/kioku-concierge-daily-pilot.md).
 *
 * Live dedupe is (user_id, dedupe_key). Character variant is fixed at
 * creation and only changes presentation.
 *
 * @property string $id
 * @property int $user_id
 * @property Carbon $week_start
 * @property string $mode
 * @property string $cadence
 * @property Carbon $delivery_date
 * @property string $dedupe_key
 * @property int|null $pilot_day
 * @property string $status
 * @property string $character_variant
 * @property string|null $intro
 * @property string|null $context
 * @property int $candidate_count
 * @property int $item_count
 * @property string $prompt_key
 * @property string|null $model
 * @property array<string, mixed>|null $generation_meta
 * @property int $retry_count
 * @property Carbon|null $generated_at
 * @property Carbon|null $published_at
 * @property Carbon|null $opened_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $halted_at
 * @property Carbon|null $halt_resolved_at
 * @property string|null $halt_resolution_note
 * @property Carbon|null $test_expires_at
 * @property string|null $evaluation_memory_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'week_start',
    'mode',
    'cadence',
    'delivery_date',
    'dedupe_key',
    'pilot_day',
    'status',
    'character_variant',
    'intro',
    'context',
    'candidate_count',
    'item_count',
    'prompt_key',
    'model',
    'generation_meta',
    'retry_count',
    'generated_at',
    'published_at',
    'opened_at',
    'completed_at',
    'halted_at',
    'halt_resolved_at',
    'halt_resolution_note',
    'test_expires_at',
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

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'mode' => 'live',
        'cadence' => 'weekly',
        'retry_count' => 0,
        'candidate_count' => 0,
        'item_count' => 0,
    ];

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
            'delivery_date' => 'date',
            'pilot_day' => 'integer',
            'candidate_count' => 'integer',
            'item_count' => 'integer',
            'retry_count' => 'integer',
            'generation_meta' => 'array',
            'generated_at' => 'datetime',
            'published_at' => 'datetime',
            'opened_at' => 'datetime',
            'completed_at' => 'datetime',
            'halted_at' => 'datetime',
            'halt_resolved_at' => 'datetime',
            'test_expires_at' => 'datetime',
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

    public function modeEnum(): KiokuLetterMode
    {
        return KiokuLetterMode::from($this->mode);
    }

    public function cadenceEnum(): KiokuLetterCadence
    {
        return KiokuLetterCadence::from($this->cadence);
    }

    public function isLive(): bool
    {
        return $this->modeEnum() === KiokuLetterMode::Live;
    }

    public function isTest(): bool
    {
        return $this->modeEnum() === KiokuLetterMode::Test;
    }

    /**
     * Unresolved sensitive halt blocks all further live/test AI generation
     * for this owner until resolve-halt runs.
     */
    public function hasUnresolvedHalt(): bool
    {
        return $this->status === self::STATUS_HALTED && $this->halt_resolved_at === null;
    }

    /**
     * Verdicts are frozen once the evaluation memory has been written
     * (or completed_at set for empty / test completions).
     */
    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }
}
