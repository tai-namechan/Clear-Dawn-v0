<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Team\Concerns\InteractsWithTeamWorkspace;
use App\Models\Team;
use App\Policies\TeamDataAccessPolicy;
use App\Queries\TeamReportQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamReportController extends Controller
{
    use InteractsWithTeamWorkspace;

    public function show(
        Request $request,
        Team $team,
        TeamDataAccessPolicy $policy,
        TeamReportQuery $query,
    ): Response {
        abort_unless($policy->accessTeam($this->actor($request), $team), 404);
        $days = (int) $request->integer('period', 7);

        return Inertia::render('Team/Reports/Show', [
            ...$this->workspaceProps($request, $team),
            ...$query->handle($team, $days),
        ]);
    }
}
