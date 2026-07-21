<?php

namespace App\Domain\Kioku\Models;

use App\Domain\Shared\Models\BelongsToUser;
use Database\Factories\Domain\Kioku\MemoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * @property string $id
 * @property int $user_id
 * @property string|null $client_capture_id
 * @property string $source_type
 * @property string|null $memory_type
 * @property string $title
 * @property string|null $raw_content
 * @property string|null $transcript_text
 * @property string|null $summary
 * @property array<string, mixed>|null $structured_data
 * @property list<string>|null $tags
 * @property Carbon $captured_at
 * @property int $importance
 * @property bool $sensitive
 * @property string $status
 * @property string|null $transcription_status
 * @property int $referenced_count
 * @property Carbon|null $last_referenced_at
 * @property Carbon|null $last_delivered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'client_capture_id',
    'source_type',
    'memory_type',
    'title',
    'raw_content',
    'transcript_text',
    'summary',
    'structured_data',
    'tags',
    'captured_at',
    'importance',
    'sensitive',
    'status',
    'transcription_status',
    'referenced_count',
    'last_referenced_at',
    'last_delivered_at',
])]
class Memory extends Model
{
    /** @use HasFactory<MemoryFactory> */
    use BelongsToUser, HasFactory, HasUlids;

    /**
     * Explicit escape hatch for admin/data-repair paths only
     * (docs/product/kioku-quick-capture.md §5). Prefer permitRawContentRepair()
     * over assigning this flag directly.
     */
    public bool $allowRawContentMutation = false;

    /**
     * Opt in to a one-off raw_content repair update. Never used in normal flows.
     */
    public function permitRawContentRepair(): static
    {
        $this->allowRawContentMutation = true;

        return $this;
    }

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'importance' => 3,
        'sensitive' => false,
        'status' => 'captured',
        'referenced_count' => 0,
    ];

    protected static function booted(): void
    {
        // raw_content is the canonical raw for manual/url memories and must
        // survive every downstream process. Guards Eloquent updates only;
        // query-builder UPDATEs bypass model events and must not touch it.
        static::updating(function (Memory $memory): void {
            if ($memory->isDirty('raw_content') && ! $memory->allowRawContentMutation) {
                throw new LogicException(
                    'Memory raw_content is immutable after creation. '
                    .'Set allowRawContentMutation for explicit data repair only.',
                );
            }
        });

        static::deleting(function (Memory $memory): void {
            $memory->assets()->get()->each(
                fn (MemoryAsset $asset) => $asset->delete(),
            );
        });

        $bumpVersion = function (Memory $memory): void {
            DB::table('users')
                ->where('id', $memory->user_id)
                ->increment('memory_version');
        };

        static::created($bumpVersion);
        static::updated(function (Memory $memory) use ($bumpVersion): void {
            if ($memory->wasChanged(['status', 'sensitive', 'tags', 'summary', 'title'])) {
                $bumpVersion($memory);
            }
        });
        static::deleted($bumpVersion);
    }

    protected static function newFactory(): MemoryFactory
    {
        return MemoryFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'structured_data' => 'array',
            'tags' => 'array',
            'captured_at' => 'datetime',
            'importance' => 'integer',
            'sensitive' => 'boolean',
            'referenced_count' => 'integer',
            'last_referenced_at' => 'datetime',
            'last_delivered_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<MemoryLink, $this>
     */
    public function outgoingLinks(): HasMany
    {
        return $this->hasMany(MemoryLink::class, 'from_memory_id');
    }

    /**
     * @return HasMany<MemoryAsset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(MemoryAsset::class);
    }

    public function audioAsset(): ?MemoryAsset
    {
        return $this->assets()->where('kind', MemoryAsset::KIND_AUDIO_ORIGINAL)->first();
    }

    /**
     * Text the enrichment pipeline should analyze: transcript for voice,
     * raw_content otherwise.
     */
    public function enrichmentSourceText(): ?string
    {
        if ($this->source_type === 'voice') {
            return $this->transcript_text;
        }

        return $this->raw_content;
    }
}
