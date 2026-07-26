<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Team\Concerns\InteractsWithTeamWorkspace;
use App\Models\Team;
use App\Models\User;
use App\Policies\TeamDataAccessPolicy;
use App\Queries\TeamAthleteConditionQuery;
use App\Queries\TeamAthleteGoalsQuery;
use App\Queries\TeamAthleteMealsQuery;
use App\Queries\TeamAthleteOverviewQuery;
use App\Queries\TeamAthleteReportQuery;
use App\Queries\TeamAthleteTrainingQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamAthleteController extends Controller
{
    use InteractsWithTeamWorkspace;

    public function show(
        Request $request,
        Team $team,
        User $athlete,
        TeamDataAccessPolicy $policy,
        TeamAthleteOverviewQuery $overview,
    ): Response {
        abort_unless($policy->viewAthlete($this->actor($request), $team, $athlete), 404);

        return Inertia::render('Team/Athletes/Show', [
            ...$this->workspaceProps($request, $team),
            'tab' => 'overview',
            'athlete' => $overview->handle($team, $athlete),
        ]);
    }

    public function training(
        Request $request,
        Team $team,
        User $athlete,
        TeamDataAccessPolicy $policy,
        TeamAthleteTrainingQuery $query,
    ): Response {
        abort_unless($policy->viewAthlete($this->actor($request), $team, $athlete), 404);

        return Inertia::render('Team/Athletes/Training', [
            ...$this->workspaceProps($request, $team),
            'tab' => 'training',
            ...$query->handle($team, $athlete),
        ]);
    }

    public function meals(
        Request $request,
        Team $team,
        User $athlete,
        TeamDataAccessPolicy $policy,
        TeamAthleteMealsQuery $query,
    ): Response {
        abort_unless($policy->viewAthlete($this->actor($request), $team, $athlete), 404);

        return Inertia::render('Team/Athletes/Meals', [
            ...$this->workspaceProps($request, $team),
            'tab' => 'meals',
            ...$query->handle($team, $athlete),
        ]);
    }

    public function condition(
        Request $request,
        Team $team,
        User $athlete,
        TeamDataAccessPolicy $policy,
        TeamAthleteConditionQuery $query,
    ): Response {
        abort_unless($policy->viewAthlete($this->actor($request), $team, $athlete), 404);

        return Inertia::render('Team/Athletes/Condition', [
            ...$this->workspaceProps($request, $team),
            'tab' => 'condition',
            ...$query->handle($team, $athlete),
        ]);
    }

    public function goals(
        Request $request,
        Team $team,
        User $athlete,
        TeamDataAccessPolicy $policy,
        TeamAthleteGoalsQuery $query,
    ): Response {
        abort_unless($policy->viewAthlete($this->actor($request), $team, $athlete), 404);

        return Inertia::render('Team/Athletes/Goals', [
            ...$this->workspaceProps($request, $team),
            'tab' => 'goals',
            ...$query->handle($team, $athlete),
        ]);
    }

    public function report(
        Request $request,
        Team $team,
        User $athlete,
        TeamDataAccessPolicy $policy,
        TeamAthleteReportQuery $query,
    ): Response {
        abort_unless($policy->viewAthlete($this->actor($request), $team, $athlete), 404);

        return Inertia::render('Team/Athletes/Report', [
            ...$this->workspaceProps($request, $team),
            'tab' => 'report',
            ...$query->handle($team, $athlete),
        ]);
    }
}
