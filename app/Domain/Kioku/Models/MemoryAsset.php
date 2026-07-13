<?php

namespace App\Domain\Kioku\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Original media backing a Memory. For voice memories the audio_original
 * asset is the canonical raw; the file itself lives on a private disk.
 *
 * @property string $id
 * @property string $memory_id
 * @property string $kind
 * @property string $disk
 * @property string $path
 * @property string $mime_type
 * @property int $byte_size
 * @property int|null $duration_ms
 * @property string|null $checksum
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'memory_id',
    'kind',
    'disk',
    'path',
    'mime_type',
    'byte_size',
    'duration_ms',
    'checksum',
])]
class MemoryAsset extends Model
{
    use HasUlids;

    public const KIND_AUDIO_ORIGINAL = 'audio_original';

    protected static function booted(): void
    {
        // Storage cleanup piggybacks on Eloquent deletes; DB-level cascades
        // bypass this, so Memory::deleting removes assets through Eloquent.
        static::deleted(function (MemoryAsset $asset): void {
            Storage::disk($asset->disk)->delete($asset->path);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'byte_size' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Memory, $this>
     */
    public function memory(): BelongsTo
    {
        return $this->belongsTo(Memory::class);
    }
}
