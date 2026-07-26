<?php

namespace App\Models;

use App\Enums\TeamMembershipRole;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use InvalidArgumentException;

class TeamMembership extends Model
{
    use HasUlids;

    protected $fillable = ['team_id', 'member_type', 'member_id', 'role', 'status', 'joined_at', 'left_at', 'invited_by_team_user_id'];

    protected static function booted(): void
    {
        static::saving(function (TeamMembership $membership): void {
            $role = $membership->role instanceof TeamMembershipRole
                ? $membership->role
                : TeamMembershipRole::from((string) $membership->role);

            if (($membership->member_type === 'user') !== ($role === TeamMembershipRole::Athlete)) {
                throw new InvalidArgumentException('The membership role is not valid for this member type.');
            }
        });
    }

    protected function casts(): array
    {
        return ['role' => TeamMembershipRole::class, 'joined_at' => 'datetime', 'left_at' => 'datetime'];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function member(): MorphTo
    {
        return $this->morphTo();
    }
}
