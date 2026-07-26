<?php

namespace App\Models;

use Database\Factories\TeamProgramAssignmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $team_program_id
 * @property int $user_id
 * @property string $status
 * @property Carbon|null $assigned_at
 */
class TeamProgramAssignment extends Model
{
    /** @use HasFactory<TeamProgramAssignmentFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'team_program_id',
        'user_id',
        'status',
        'assigned_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<TeamProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(TeamProgram::class, 'team_program_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
