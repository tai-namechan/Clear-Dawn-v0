<?php

namespace App\Domain\Kioku\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $from_memory_id
 * @property string $to_memory_id
 * @property string $kind
 * @property float|null $score
 * @property string $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['from_memory_id', 'to_memory_id', 'kind', 'score', 'created_by'])]
class MemoryLink extends Model
{
    use HasUlids;

    /**
     * @return BelongsTo<Memory, $this>
     */
    public function fromMemory(): BelongsTo
    {
        return $this->belongsTo(Memory::class, 'from_memory_id');
    }

    /**
     * @return BelongsTo<Memory, $this>
     */
    public function toMemory(): BelongsTo
    {
        return $this->belongsTo(Memory::class, 'to_memory_id');
    }
}
