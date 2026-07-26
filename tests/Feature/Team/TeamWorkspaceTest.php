<?php

namespace Tests\Feature\Team;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMembership;
use App\Models\TeamUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as OAuthUser;
use Mockery;
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

    public function test_uninvited_google_user_cannot_authenticate(): void
    {
        config()->set('services.google_team_auth.enabled', true);
        $this->mockGoogleCallback('uninvited@example.com');

        $this->get('http://team.localhost/auth/google/callback')
            ->assertRedirect('http://team.localhost/login')
            ->assertSessionHasErrors('google');

        $this->assertGuest('team');
        $this->assertDatabaseMissing('team_users', ['email' => 'uninvited@example.com']);
    }

    public function test_invited_google_user_can_authenticate_and_accept_invitation(): void
    {
        config()->set('services.google_team_auth.enabled', true);
        $inviter = TeamUser::factory()->create();
        $team = Team::factory()->create(['created_by_team_user_id' => $inviter->id]);
        TeamInvitation::query()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'role' => 'coach',
            'invitee_type' => 'team_user',
            'token_hash' => hash('sha256', 'team-test-invitation'),
            'invited_by_team_user_id' => $inviter->id,
            'expires_at' => now()->addDay(),
        ]);
        $this->mockGoogleCallback('Invited@Example.com');

        $this->get('http://team.localhost/auth/google/callback')
            ->assertRedirect('http://team.localhost');

        $teamUser = TeamUser::query()->where('email', 'invited@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($teamUser, 'team');
        $this->assertDatabaseHas('team_memberships', [
            'team_id' => $team->id,
            'member_type' => 'team_user',
            'member_id' => $teamUser->id,
            'role' => 'coach',
            'status' => 'active',
        ]);
    }

    public function test_athlete_response_excludes_sensitive_record_details(): void
    {
        [$actor, $team] = $this->teamWithActor();
        $athlete = $this->addAthlete($team, 'Privacy Athlete');

        $this->actingAs($actor, 'team')
            ->get("http://team.localhost/t/{$team->slug}/athletes/{$athlete->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Team/Athletes/Show')
                ->missing('athlete.email')
                ->missing('athlete.weight')
                ->missing('athlete.meals')
                ->missing('athlete.symptoms')
                ->missing('athlete.memories')
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

    private function mockGoogleCallback(string $email): void
    {
        $oauthUser = OAuthUser::fake([
            'id' => 'google-'.strtolower($email),
            'name' => 'Google Reviewer',
            'email' => $email,
            'email_verified' => true,
            'avatar' => null,
        ]);
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')->once()->andReturn($oauthUser);
        Socialite::shouldReceive('buildProvider')
            ->once()
            ->with(GoogleProvider::class, Mockery::type('array'))
            ->andReturn($provider);
    }
}
