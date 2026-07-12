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

/**
 * @property string $id
 * @property int $user_id
 * @property string $source_type
 * @property string|null $memory_type
 * @property string $title
 * @property string $raw_content
 * @property string|null $summary
 * @property array<string, mixed>|null $structured_data
 * @property list<string>|null $tags
 * @property Carbon $captured_at
 * @property int $importance
 * @property bool $sensitive
 * @property string $status
 * @property int $referenced_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id',
    'source_type',
    'memory_type',
    'title',
    'raw_content',
    'summary',
    'structured_data',
    'tags',
    'captured_at',
    'importance',
    'sensitive',
    'status',
    'referenced_count',
])]
class Memory extends Model
{
    /** @use HasFactory<MemoryFactory> */
    use BelongsToUser, HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'importance' => 3,
        'sensitive' => false,
        'status' => 'captured',
        'referenced_count' => 0,
    ];

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
        ];
    }

    /**
     * @return HasMany<MemoryLink, $this>
     */
    public function outgoingLinks(): HasMany
    {
        return $this->hasMany(MemoryLink::class, 'from_memory_id');
    }
}
