<?php

namespace App\Domain\Kioku\Models;

use App\Domain\Shared\Models\BelongsToUser;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $memory_id
 * @property string $provider
 * @property string $model
 * @property int $dimensions
 * @property string $schema_version
 * @property string $content_hash
 * @property string|null $vector
 * @property string $status
 * @property int|null $input_tokens
 * @property string|null $actual_usd
 * @property Carbon|null $embedded_at
 * @property string|null $error_code
 * @property Carbon|null $claimed_at
 */
class MemoryEmbedding extends Model
{
    use BelongsToUser, HasUlids;

    protected $table = 'memory_embeddings';

    protected $fillable = [
        'user_id',
        'memory_id',
        'provider',
        'model',
        'dimensions',
        'schema_version',
        'content_hash',
        'vector',
        'status',
        'input_tokens',
        'actual_usd',
        'embedded_at',
        'error_code',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'dimensions' => 'integer',
            'input_tokens' => 'integer',
            'embedded_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Memory, $this>
     */
    public function memory(): BelongsTo
    {
        return $this->belongsTo(Memory::class);
    }

    /**
     * @return list<float>
     */
    public function vectorArray(): array
    {
        if ($this->vector === null || $this->vector === '') {
            return [];
        }
        $decoded = json_decode($this->vector, true);
        if (! is_array($decoded)) {
            return [];
        }

        return array_map(static fn ($v): float => (float) $v, array_values($decoded));
    }

    /**
     * @param  list<float>|array<int, float>  $vector
     */
    public function setVectorArray(array $vector): void
    {
        $list = array_values($vector);
        $this->vector = json_encode($list, JSON_THROW_ON_ERROR);
        $this->dimensions = count($list);
    }
}
