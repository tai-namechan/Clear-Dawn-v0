<?php

namespace App\Models;

use Database\Factories\TeamProgramFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $team_id
 * @property string $title
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property string $visibility_status
 * @property string|null $summary
 */
class TeamProgram extends Model
{
    /** @use HasFactory<TeamProgramFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'team_id',
        'title',
        'starts_on',
        'ends_on',
        'visibility_status',
        'summary',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return HasMany<TeamProgramItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(TeamProgramItem::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<TeamProgramAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TeamProgramAssignment::class);
    }
}
