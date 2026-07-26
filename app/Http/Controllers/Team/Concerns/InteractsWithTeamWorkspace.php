<?php

namespace App\Http\Controllers\Team\Concerns;

use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\TeamUser;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

trait InteractsWithTeamWorkspace
{
    private function actor(Request $request): TeamUser
    {
        /** @var TeamUser $actor */
        $actor = $request->user('team');

        return $actor;
    }

    /** @return Collection<int, Team> */
    private function teamsFor(Request $request): Collection
    {
        return Team::query()
            ->where('status', 'active')
            ->whereIn(
                'id',
                TeamMembership::query()
                    ->where('member_type', 'team_user')
                    ->where('member_id', $this->actor($request)->id)
                    ->where('status', 'active')
                    ->select('team_id'),
            )
            ->get(['name', 'slug']);
    }

    /** @return array{team: array{name: string, slug: string}, actor: array{name: string, email: string}} */
    private function workspaceProps(Request $request, Team $team): array
    {
        $actor = $this->actor($request);

        return [
            'team' => $team->only(['name', 'slug']),
            'actor' => $actor->only(['name', 'email']),
        ];
    }
}
