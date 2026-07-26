<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Team\Concerns\InteractsWithTeamWorkspace;
use App\Models\Team;
use App\Policies\TeamDataAccessPolicy;
use App\Queries\TeamSettingsQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamSettingsController extends Controller
{
    use InteractsWithTeamWorkspace;

    public function show(
        Request $request,
        Team $team,
        TeamDataAccessPolicy $policy,
        TeamSettingsQuery $query,
    ): Response {
        abort_unless($policy->accessTeam($this->actor($request), $team), 404);

        return Inertia::render('Team/Settings/Show', [
            ...$this->workspaceProps($request, $team),
            ...$query->handle($team),
        ]);
    }
}
