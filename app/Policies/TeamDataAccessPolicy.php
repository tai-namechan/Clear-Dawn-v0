<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\TeamUser;
use App\Models\User;

class TeamDataAccessPolicy
{
    public function accessTeam(TeamUser $actor, Team $team): bool
    {
        return $team->status === 'active' && $team->memberships()
            ->where('member_type', 'team_user')
            ->where('member_id', $actor->id)
            ->where('status', 'active')
            ->whereIn('role', ['owner', 'admin', 'head_coach', 'coach', 'nutrition_staff', 'conditioning_staff'])
            ->exists();
    }

    public function viewAthlete(TeamUser $actor, Team $team, User $athlete): bool
    {
        return $this->accessTeam($actor, $team) && $team->memberships()
            ->where('member_type', 'user')
            ->where('member_id', $athlete->id)
            ->where('role', 'athlete')
            ->where('status', 'active')
            ->exists();
    }
}
