<?php

namespace App\Models;

use Database\Factories\TeamProgramItemFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $team_program_id
 * @property string $title
 * @property int $sort_order
 */
class TeamProgramItem extends Model
{
    /** @use HasFactory<TeamProgramItemFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'team_program_id',
        'title',
        'sort_order',
    ];

    /**
     * @return BelongsTo<TeamProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(TeamProgram::class, 'team_program_id');
    }
}
