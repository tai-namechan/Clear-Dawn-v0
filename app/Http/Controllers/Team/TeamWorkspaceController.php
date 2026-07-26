<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Team\Concerns\InteractsWithTeamWorkspace;
use App\Models\Team;
use App\Models\User;
use App\Policies\TeamDataAccessPolicy;
use App\Queries\TeamRosterQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamWorkspaceController extends Controller
{
    use InteractsWithTeamWorkspace;

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
        abort_unless($policy->accessTeam($this->actor($request), $team), 404);
        $athletes = $roster->handle($team, perPage: 100)->getCollection();

        return Inertia::render('Team/Dashboard', [
            ...$this->workspaceProps($request, $team),
            'summary' => [
                'athletes' => $athletes->count(),
                'training_records' => $athletes->sum('routine_sessions_last_7_days_count'),
                'meal_record_days' => $athletes->sum('meal_days_last_7_days_count'),
                'needs_follow_up' => $athletes->filter(
                    fn (User $user): bool => $user->meal_days_last_7_days_count < 3,
                )->count(),
            ],
        ]);
    }

    public function athletes(Request $request, Team $team, TeamDataAccessPolicy $policy, TeamRosterQuery $roster): Response
    {
        abort_unless($policy->accessTeam($this->actor($request), $team), 404);
        $athletes = $roster->handle($team, $request->string('search')->trim()->toString());

        return Inertia::render('Team/Athletes/Index', [
            ...$this->workspaceProps($request, $team),
            'filters' => ['search' => $request->input('search')],
            'athletes' => $athletes->through(fn (User $athlete): array => [
                'id' => $athlete->id,
                'name' => $athlete->name,
                'training_sessions_7_days' => $athlete->routine_sessions_last_7_days_count,
                'meal_record_days_7_days' => $athlete->meal_days_last_7_days_count,
                'last_recorded_at' => max($athlete->last_meal_recorded_on, $athlete->last_training_recorded_at),
                'status' => 'active',
            ]),
        ]);
    }
}
