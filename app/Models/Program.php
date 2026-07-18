<?php

namespace App\Models;

use App\Enums\ProgramStatus;
use App\Enums\ProgramVersionStatus;
use Database\Factories\ProgramFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property int $user_id
 * @property string|null $goal_id
 * @property string $name
 * @property string|null $purpose
 * @property string|null $design_philosophy
 * @property ProgramStatus $status
 */
#[Fillable([
    'user_id',
    'goal_id',
    'name',
    'purpose',
    'design_philosophy',
    'status',
])]
class Program extends Model
{
    /** @use HasFactory<ProgramFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProgramStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Goal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /**
     * @return HasMany<ProgramVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ProgramVersion::class)->orderByDesc('version_number');
    }

    /**
     * @return HasOne<ProgramVersion, $this>
     */
    public function activeVersion(): HasOne
    {
        return $this->hasOne(ProgramVersion::class)
            ->where('status', ProgramVersionStatus::Active)
            ->orderByDesc('version_number');
    }
}
