<?php

namespace App\Domain\Kioku\Models;

use Database\Factories\Domain\Kioku\KiokuLetterItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One surfaced memory inside a concierge letter. Title/summary are
 * generation-time snapshots so the letter stays readable even if the source
 * memory is re-enriched later; ownership is derived through the letter.
 *
 * @property string $id
 * @property string $letter_id
 * @property string $memory_id
 * @property int $position
 * @property string $title_snapshot
 * @property string|null $summary_snapshot
 * @property string $headline
 * @property string $why_now
 * @property list<string>|null $related_memory_ids
 * @property string|null $verdict
 * @property string|null $verdict_note
 * @property Carbon|null $verdict_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'letter_id',
    'memory_id',
    'position',
    'title_snapshot',
    'summary_snapshot',
    'headline',
    'why_now',
    'related_memory_ids',
    'verdict',
    'verdict_note',
    'verdict_at',
])]
class KiokuLetterItem extends Model
{
    /** @use HasFactory<KiokuLetterItemFactory> */
    use HasFactory, HasUlids;

    public const VERDICT_HIT = 'hit';

    public const VERDICT_SOFT_HIT = 'soft_hit';

    public const VERDICT_MISS = 'miss';

    public const VERDICT_SENSITIVE_LEAK = 'sensitive_leak';

    public const VERDICTS = [
        self::VERDICT_HIT,
        self::VERDICT_SOFT_HIT,
        self::VERDICT_MISS,
        self::VERDICT_SENSITIVE_LEAK,
    ];

    protected static function newFactory(): KiokuLetterItemFactory
    {
        return KiokuLetterItemFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'related_memory_ids' => 'array',
            'verdict_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<KiokuLetter, $this>
     */
    public function letter(): BelongsTo
    {
        return $this->belongsTo(KiokuLetter::class, 'letter_id');
    }

    /**
     * @return BelongsTo<Memory, $this>
     */
    public function memory(): BelongsTo
    {
        return $this->belongsTo(Memory::class, 'memory_id');
    }
}
