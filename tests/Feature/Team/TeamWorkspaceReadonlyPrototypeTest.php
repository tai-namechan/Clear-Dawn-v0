<?php

namespace Tests\Feature\Team;

use App\Enums\GoalStatus;
use App\Enums\MealType;
use App\Enums\RoutineSessionStatus;
use App\Models\DailyCheckin;
use App\Models\Goal;
use App\Models\MealEntry;
use App\Models\RoutinePlan;
use App\Models\RoutineSession;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMembership;
use App\Models\TeamProgram;
use App\Models\TeamProgramAssignment;
use App\Models\TeamProgramItem;
use App\Models\TeamUser;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\TeamWorkspaceDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamWorkspaceReadonlyPrototypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_staff_can_view_programs_reports_and_settings(): void
    {
        [$actor, $team] = $this->teamWithActor();
        $athlete = $this->addAthlete($team, 'Program Athlete');
        $program = TeamProgram::factory()->create([
            'team_id' => $team->id,
            'title' => '基礎練プログラム',
            'visibility_status' => 'published',
        ]);
        TeamProgramItem::factory()->create([
            'team_program_id' => $program->id,
            'title' => '体幹',
            'sort_order' => 1,
        ]);
        TeamProgramAssignment::factory()->create([
            'team_program_id' => $program->id,
            'user_id' => $athlete->id,
        ]);

        $this->actingAs($actor, 'team')
            ->get("http://team.localhost/t/{$team->slug}/programs")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Team/Programs/Index')
                ->has('programs', 1)
                ->where('programs.0.title', '基礎練プログラム')
                ->where('programs.0.assigned_count', 1)
            );

        $this->actingAs($actor, 'team')
            ->get("http://team.localhost/t/{$team->slug}/reports")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Team/Reports/Show')
                ->where('summary.athletes', 1)
                ->where('disclaimer', fn ($value) => str_contains((string) $value, '診断ではありません'))
            );

        $this->actingAs($actor, 'team')
            ->get("http://team.localhost/t/{$team->slug}/settings")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Team/Settings/Show')
                ->where('team.name', $team->name)
                ->has('staff', 1)
                ->where('prototype_note', fn ($value) => str_contains((string) $value, '編集対象外'))
            );
    }

    public function test_athlete_role_membership_cannot_access_management_pages(): void
    {
        $actor = TeamUser::factory()->create();
        $team = Team::factory()->create(['created_by_team_user_id' => $actor->id]);
        DB::table('team_memberships')->insert([
            'id' => (string) Str::ulid(),
            'team_id' => $team->id,
            'member_type' => 'team_user',
            'member_id' => $actor->id,
            'role' => 'athlete',
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($actor, 'team')->get("http://team.localhost/t/{$team->slug}/programs")->assertNotFound();
        $this->actingAs($actor, 'team')->get("http://team.localhost/t/{$team->slug}/reports")->assertNotFound();
        $this->actingAs($actor, 'team')->get("http://team.localhost/t/{$team->slug}/settings")->assertNotFound();
    }

    public function test_other_team_resources_are_not_visible(): void
    {
        [$actor, $team] = $this->teamWithActor();
        $otherTeam = Team::factory()->create();
        $otherAthlete = $this->addAthlete($otherTeam, 'Other Team Athlete');
        TeamProgram::factory()->create([
            'team_id' => $otherTeam->id,
            'title' => '他チームのプログラム',
        ]);

        $this->actingAs($actor, 'team')
            ->get("http://team.localhost/t/{$otherTeam->slug}/programs")
            ->assertNotFound();
        $this->actingAs($actor, 'team')
            ->get("http://team.localhost/t/{$otherTeam->slug}/reports")
            ->assertNotFound();
        $this->actingAs($actor, 'team')
            ->get("http://team.localhost/t/{$otherTeam->slug}/settings")
            ->assertNotFound();
        $this->actingAs($actor, 'team')
            ->get("http://team.localhost/t/{$team->slug}/athletes/{$otherAthlete->id}/training")
            ->assertNotFound();

        $this->actingAs($actor, 'team')
            ->get("http://team.localhost/t/{$team->slug}/programs")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('programs', 0));
    }

    public function test_inactive_membership_and_inactive_team_are_rejected(): void
    {
        [$actor, $team, $membership] = $this->teamWithActor();
        $membership->update(['status' => 'suspended']);
        $this->actingAs($actor, 'team')->get("http://team.localhost/t/{$team->slug}/reports")->assertNotFound();

        $membership->update(['status' => 'active']);
        $team->update(['status' => 'inactive']);
        $this->actingAs($actor, 'team')->get("http://team.localhost/t/{$team->slug}/settings")->assertNotFound();
    }

    public function test_athlete_detail_tabs_return_safe_aggregates_and_exclude_sensitive_props(): void
    {
        [$actor, $team] = $this->teamWithActor();
        $athlete = $this->addAthlete($team, 'Detail Athlete');
        $timezone = $team->timezone ?: 'Asia/Tokyo';
        $today = CarbonImmutable::now($timezone)->startOfDay();

        $plan = RoutinePlan::factory()->create([
            'user_id' => $athlete->id,
            'title' => 'スピード練',
            'scheduled_on' => $today->toDateString(),
        ]);
        RoutineSession::factory()->create([
            'user_id' => $athlete->id,
            'routine_plan_id' => $plan->id,
            'status' => RoutineSessionStatus::Completed,
            'started_at' => $today->setTime(16, 0),
            'finished_at' => $today->setTime(17, 15),
            'session_rpe' => 7.0,
            'note' => 'SECRET_SESSION_NOTE',
        ]);
        MealEntry::factory()->create([
            'user_id' => $athlete->id,
            'eaten_on' => $today->toDateString(),
            'meal_type' => MealType::Breakfast,
            'name' => 'SECRET_FOOD',
            'quantity' => 200,
            'note' => 'SECRET_MEAL_NOTE',
        ]);
        DailyCheckin::factory()->create([
            'user_id' => $athlete->id,
            'checked_on' => $today->toDateString(),
            'readiness_self' => 8,
            'fatigue' => 2,
            'note' => 'SECRET_CONDITION_NOTE',
        ]);
        Goal::factory()->create([
            'user_id' => $athlete->id,
            'name' => '公開目標',
            'why' => 'SECRET_WHY',
            'status' => GoalStatus::Achieved,
            'deadline' => $today->addDays(30)->toDateString(),
        ]);

        $paths = [
            "http://team.localhost/t/{$team->slug}/athletes/{$athlete->id}" => 'Team/Athletes/Show',
            "http://team.localhost/t/{$team->slug}/athletes/{$athlete->id}/training" => 'Team/Athletes/Training',
            "http://team.localhost/t/{$team->slug}/athletes/{$athlete->id}/meals" => 'Team/Athletes/Meals',
            "http://team.localhost/t/{$team->slug}/athletes/{$athlete->id}/condition" => 'Team/Athletes/Condition',
            "http://team.localhost/t/{$team->slug}/athletes/{$athlete->id}/goals" => 'Team/Athletes/Goals',
            "http://team.localhost/t/{$team->slug}/athletes/{$athlete->id}/report" => 'Team/Athletes/Report',
        ];

        foreach ($paths as $url => $component) {
            $this->actingAs($actor, 'team')
                ->get($url)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($component)
                    ->missing('athlete.email')
                    ->missing('athlete.weight')
                    ->missing('athlete.meals')
                    ->missing('athlete.symptoms')
                    ->missing('athlete.memories')
                    ->where('athlete.name', 'Detail Athlete')
                );
        }

        $this->actingAs($actor, 'team')
            ->get("http://team.localhost/t/{$team->slug}/athletes/{$athlete->id}/training")
            ->assertInertia(fn (Assert $page) => $page
                ->has('sessions', 1)
                ->where('sessions.0.category', 'スピード練')
                ->where('sessions.0.duration_minutes', 75)
                ->where('sessions.0.intensity', '強め')
                ->where('sessions.0.status_label', '完了')
                ->missing('sessions.0.note')
            );

        $this->actingAs($actor, 'team')
            ->get("http://team.localhost/t/{$team->slug}/athletes/{$athlete->id}/meals")
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.recorded_days', 1)
                ->where('summary.total_meals', 1)
                ->missing('days.0.name')
                ->missing('days.0.quantity')
                ->missing('days.0.note')
            );

        $this->actingAs($actor, 'team')
            ->get("http://team.localhost/t/{$team->slug}/athletes/{$athlete->id}/condition")
            ->assertInertia(fn (Assert $page) => $page
                ->has('entries', 1)
                ->where('entries.0.recovery_level', '回復寄り')
                ->missing('entries.0.note')
                ->missing('entries.0.readiness_self')
            );

        $this->actingAs($actor, 'team')
            ->get("http://team.localhost/t/{$team->slug}/athletes/{$athlete->id}/goals")
            ->assertInertia(fn (Assert $page) => $page
                ->has('goals', 1)
                ->where('goals.0.name', '公開目標')
                ->where('goals.0.progress_percent', 100)
                ->missing('goals.0.why')
            );

        $this->actingAs($actor, 'team')
            ->get("http://team.localhost/t/{$team->slug}/athletes/{$athlete->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('athlete.training_sessions_7_days', 1)
                ->where('athlete.meal_record_days_7_days', 1)
                ->where('athlete.goals_achieved_count', 1)
            );
    }

    public function test_empty_states_and_period_boundaries(): void
    {
        [$actor, $team] = $this->teamWithActor();
        $athlete = $this->addAthlete($team, 'Boundary Athlete');
        $timezone = $team->timezone ?: 'Asia/Tokyo';
        $today = CarbonImmutable::now($timezone)->startOfDay();

        MealEntry::factory()->create([
            'user_id' => $athlete->id,
            'eaten_on' => $today->subDays(20)->toDateString(),
            'meal_type' => MealType::Lunch,
        ]);

        $this->actingAs($actor, 'team')
            ->get("http://team.localhost/t/{$team->slug}/athletes/{$athlete->id}/meals")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.recorded_days', 0)
                ->has('days', 14)
            );

        MealEntry::factory()->create([
            'user_id' => $athlete->id,
            'eaten_on' => $today->subDays(8)->toDateString(),
            'meal_type' => MealType::Dinner,
        ]);

        $this->actingAs($actor, 'team')
            ->get("http://team.localhost/t/{$team->slug}/programs")
            ->assertInertia(fn (Assert $page) => $page->has('programs', 0));

        $this->actingAs($actor, 'team')
            ->get("http://team.localhost/t/{$team->slug}/reports?period=30")
            ->assertInertia(fn (Assert $page) => $page
                ->where('period.days', 30)
                ->where('period.follow_up_threshold_days', 10)
                ->where('summary.meal_record_days', 2)
            );

        $this->actingAs($actor, 'team')
            ->get("http://team.localhost/t/{$team->slug}/reports?period=7")
            ->assertInertia(fn (Assert $page) => $page
                ->where('period.days', 7)
                ->where('summary.meal_record_days', 0)
            );
    }

    public function test_settings_masks_invitation_email(): void
    {
        [$actor, $team] = $this->teamWithActor();
        TeamInvitation::query()->create([
            'team_id' => $team->id,
            'email' => 'pending.staff@example.com',
            'role' => 'coach',
            'invitee_type' => 'team_user',
            'token_hash' => hash('sha256', 'settings-invite'),
            'invited_by_team_user_id' => $actor->id,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($actor, 'team')
            ->get("http://team.localhost/t/{$team->slug}/settings")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('invitations', 1)
                ->where('invitations.0.email_masked', 'p***@example.com')
                ->missing('invitations.0.email')
            );
    }

    public function test_demo_seeder_produces_expected_readonly_workspace(): void
    {
        $this->seed(TeamWorkspaceDemoSeeder::class);
        $actor = TeamUser::query()->where('email', 'coach@team.local')->firstOrFail();
        $team = Team::query()->where('slug', 'clear-dawn-juniors')->firstOrFail();

        $this->actingAs($actor, 'team')
            ->get("http://team.localhost/t/{$team->slug}/dashboard")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.athletes', 3)
                ->where('summary.training_records', fn ($value) => $value >= 1)
                ->where('summary.meal_record_days', 13)
            );

        $this->actingAs($actor, 'team')
            ->get("http://team.localhost/t/{$team->slug}/programs")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('programs', 2)
                ->where('programs.0.title', fn ($value) => is_string($value) && $value !== '')
            );

        $athlete = User::query()->where('email', 'yota.athlete@demo.local')->firstOrFail();
        $this->actingAs($actor, 'team')
            ->get("http://team.localhost/t/{$team->slug}/athletes/{$athlete->id}/training")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('sessions'));
    }

    /** @return array{0: TeamUser, 1: Team, 2: TeamMembership} */
    private function teamWithActor(): array
    {
        $actor = TeamUser::factory()->create();
        $team = Team::factory()->create(['created_by_team_user_id' => $actor->id, 'timezone' => 'Asia/Tokyo']);
        $membership = TeamMembership::query()->create([
            'team_id' => $team->id,
            'member_type' => 'team_user',
            'member_id' => $actor->id,
            'role' => 'coach',
            'status' => 'active',
        ]);

        return [$actor, $team, $membership];
    }

    private function addAthlete(Team $team, string $name, string $status = 'active'): User
    {
        $athlete = User::factory()->create(['name' => $name]);
        TeamMembership::query()->create([
            'team_id' => $team->id,
            'member_type' => 'user',
            'member_id' => $athlete->id,
            'role' => 'athlete',
            'status' => $status,
        ]);

        return $athlete;
    }
}
