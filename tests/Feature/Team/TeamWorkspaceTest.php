<?php

namespace Tests\Feature\Team;

use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\TeamUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\TestCase;

class TeamWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_session_does_not_authenticate_team_workspace(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'web')->get('http://team.localhost/')->assertRedirect('http://team.localhost/login');
    }

    public function test_team_user_only_sees_active_athletes_in_their_team(): void
    {
        [$actor, $team] = $this->teamWithActor();
        $visible = $this->addAthlete($team, 'Visible Athlete');
        $inactive = $this->addAthlete($team, 'Inactive Athlete', 'suspended');
        $otherTeam = Team::factory()->create();
        $hidden = $this->addAthlete($otherTeam, 'Hidden Athlete');

        $this->actingAs($actor, 'team')->get("http://team.localhost/t/{$team->slug}/athletes")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Team/Athletes/Index')
                ->has('athletes.data', 1)
                ->where('athletes.data.0.id', $visible->id)
                ->where('athletes.data.0.name', 'Visible Athlete')
            );

        $this->assertNotSame($visible->id, $inactive->id);
        $this->assertNotSame($visible->id, $hidden->id);
    }

    public function test_dashboard_returns_all_four_summary_cards(): void
    {
        [$actor, $team] = $this->teamWithActor();
        $this->addAthlete($team, 'Dashboard Athlete');

        $this->actingAs($actor, 'team')->get("http://team.localhost/t/{$team->slug}/dashboard")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Team/Dashboard')
                ->where('summary.athletes', 1)
                ->where('summary.training_records', 0)
                ->where('summary.meal_record_days', 0)
                ->where('summary.needs_follow_up', 1)
            );
    }

    public function test_team_user_cannot_open_an_athlete_from_another_team(): void
    {
        [$actor, $team] = $this->teamWithActor();
        $otherTeam = Team::factory()->create();
        $otherAthlete = $this->addAthlete($otherTeam, 'Other Athlete');

        $this->actingAs($actor, 'team')
            ->get("http://team.localhost/t/{$team->slug}/athletes/{$otherAthlete->id}")
            ->assertNotFound();
    }

    public function test_suspended_team_membership_cannot_access_team(): void
    {
        [$actor, $team, $membership] = $this->teamWithActor();
        $membership->update(['status' => 'suspended']);

        $this->actingAs($actor, 'team')->get("http://team.localhost/t/{$team->slug}/dashboard")->assertNotFound();
    }

    public function test_member_type_and_role_must_match(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $team = Team::factory()->create();
        $user = User::factory()->create();

        TeamMembership::query()->create(['team_id' => $team->id, 'member_type' => 'user', 'member_id' => $user->id, 'role' => 'coach', 'status' => 'active']);
    }

    private function teamWithActor(): array
    {
        $actor = TeamUser::factory()->create();
        $team = Team::factory()->create(['created_by_team_user_id' => $actor->id]);
        $membership = TeamMembership::query()->create(['team_id' => $team->id, 'member_type' => 'team_user', 'member_id' => $actor->id, 'role' => 'coach', 'status' => 'active']);

        return [$actor, $team, $membership];
    }

    private function addAthlete(Team $team, string $name, string $status = 'active'): User
    {
        $athlete = User::factory()->create(['name' => $name]);
        TeamMembership::query()->create(['team_id' => $team->id, 'member_type' => 'user', 'member_id' => $athlete->id, 'role' => 'athlete', 'status' => $status]);

        return $athlete;
    }
}
