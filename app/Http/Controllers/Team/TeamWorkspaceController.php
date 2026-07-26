<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\TeamUser;
use App\Models\User;
use App\Policies\TeamDataAccessPolicy;
use App\Queries\TeamAthleteOverviewQuery;
use App\Queries\TeamRosterQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamWorkspaceController extends Controller
{
    public function home(Request $request): RedirectResponse|Response
    {
        $teams = $this->teamsFor($request);
        if ($teams->count() === 1) {
            return redirect()->route('team.dashboard', $teams->first());
        }

        return Inertia::render('Team/Select', ['teams' => $teams]);
    }

    public function dashboard(Request $request, Team $team, TeamDataAccessPolicy $policy, TeamRosterQuery $roster): Response
    {
        $actor = $this->actor($request);
        abort_unless($policy->accessTeam($actor, $team), 404);
        $athletes = $roster->handle($team, perPage: 100)->getCollection();

        return Inertia::render('Team/Dashboard', ['team' => $team->only(['name', 'slug']), 'actor' => $actor->only(['name', 'email']), 'summary' => ['athletes' => $athletes->count(), 'training_records' => $athletes->sum('routine_sessions_last_7_days_count'), 'meal_record_days' => $athletes->sum('meal_days_last_7_days_count'), 'needs_follow_up' => $athletes->filter(fn (User $user) => $user->meal_days_last_7_days_count < 3)->count()]]);
    }

    public function athletes(Request $request, Team $team, TeamDataAccessPolicy $policy, TeamRosterQuery $roster): Response
    {
        $actor = $this->actor($request);
        abort_unless($policy->accessTeam($actor, $team), 404);
        $athletes = $roster->handle($team, $request->string('search')->trim()->toString());

        return Inertia::render('Team/Athletes/Index', ['team' => $team->only(['name', 'slug']), 'actor' => $actor->only(['name', 'email']), 'filters' => ['search' => $request->input('search')], 'athletes' => $athletes->through(fn (User $athlete) => ['id' => $athlete->id, 'name' => $athlete->name, 'training_sessions_7_days' => $athlete->routine_sessions_last_7_days_count, 'meal_record_days_7_days' => $athlete->meal_days_last_7_days_count, 'last_recorded_at' => max($athlete->last_meal_recorded_on, $athlete->last_training_recorded_at), 'status' => 'active'])]);
    }

    public function athlete(Request $request, Team $team, User $athlete, TeamDataAccessPolicy $policy, TeamAthleteOverviewQuery $overview): Response
    {
        $actor = $this->actor($request);
        abort_unless($policy->viewAthlete($actor, $team, $athlete), 404);

        return Inertia::render('Team/Athletes/Show', ['team' => $team->only(['name', 'slug']), 'actor' => $actor->only(['name', 'email']), 'athlete' => $overview->handle($athlete)]);
    }

    private function actor(Request $request): TeamUser
    {
        /** @var TeamUser $actor */
        $actor = $request->user('team');

        return $actor;
    }

    private function teamsFor(Request $request)
    {
        return Team::query()->where('status', 'active')->whereIn('id', TeamMembership::query()->where('member_type', 'team_user')->where('member_id', $this->actor($request)->id)->where('status', 'active')->select('team_id'))->get(['name', 'slug']);
    }
}
