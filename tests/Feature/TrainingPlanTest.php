<?php

namespace Tests\Feature;

use App\Enums\TrainingPlanStatus;
use App\Models\Exercise;
use App\Models\Routine;
use App\Models\RoutineStep;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanStep;
use App\Models\TrainingRun;
use App\Models\User;
use Database\Seeders\MatrixRowSeeder;
use Database\Seeders\MetricSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TrainingPlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MatrixRowSeeder::class);
        $this->seed(MetricSeeder::class);
    }

    public function test_guests_cannot_access_training_plans(): void
    {
        $plan = TrainingPlan::factory()->create();

        $this->get(route('training.index'))->assertRedirect(route('login'));
        $this->postJson(route('training-plans.store'), [
            'title' => '新規',
            'scheduled_on' => now()->toDateString(),
        ])->assertUnauthorized();
        $this->get(route('training-plans.show', $plan))->assertRedirect(route('login'));
        $this->deleteJson(route('training-plans.destroy', $plan))->assertUnauthorized();
    }

    public function test_user_can_create_an_empty_training_plan(): void
    {
        $user = User::factory()->create();
        $date = '2026-07-07';

        $response = $this->actingAs($user)->postJson(route('training-plans.store'), [
            'title' => '空のプラン',
            'scheduled_on' => $date,
        ]);

        $response->assertOk()
            ->assertJsonPath('plan.title', '空のプラン')
            ->assertJsonPath('plan.status', TrainingPlanStatus::Draft->value);

        $plan = TrainingPlan::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(0, $plan->steps()->count());
        $this->assertSame($date, $plan->scheduled_on->toDateString());
    }

    public function test_user_can_create_a_plan_with_steps_snapshotted_from_a_routine(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id]);
        $step = RoutineStep::factory()->forRoutine($routine)->create([
            'sort_order' => 1,
            'target_sets' => 3,
            'target_reps' => 10,
        ]);

        $response = $this->actingAs($user)->postJson(route('training-plans.store'), [
            'title' => 'ルーティンから作成',
            'scheduled_on' => '2026-07-07',
            'routine_id' => $routine->id,
        ]);

        $response->assertOk()->assertJsonPath('plan.routine_id', $routine->id);

        $plan = TrainingPlan::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(1, $plan->steps()->count());
        $this->assertDatabaseHas('training_plan_steps', [
            'training_plan_id' => $plan->id,
            'exercise_id' => $step->exercise_id,
            'target_sets' => 3,
            'target_reps' => 10,
            'sort_order' => 1,
        ]);
    }

    public function test_editing_a_routine_after_plan_creation_does_not_change_plan_steps(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id]);
        $step = RoutineStep::factory()->forRoutine($routine)->create([
            'target_reps' => 10,
            'sort_order' => 1,
        ]);

        $this->actingAs($user)->postJson(route('training-plans.store'), [
            'title' => 'スナップショット',
            'scheduled_on' => '2026-07-07',
            'routine_id' => $routine->id,
        ])->assertOk();

        $plan = TrainingPlan::query()->where('user_id', $user->id)->firstOrFail();
        $planStepId = $plan->steps()->firstOrFail()->id;

        $this->actingAs($user)->patchJson(route('routine-steps.update', [$routine, $step]), [
            'target_reps' => 20,
        ])->assertOk();

        $this->assertDatabaseHas('training_plan_steps', [
            'id' => $planStepId,
            'target_reps' => 10,
        ]);
    }

    public function test_user_can_create_multiple_plans_for_the_same_day(): void
    {
        $user = User::factory()->create();
        $date = '2026-07-07';

        $this->actingAs($user)->postJson(route('training-plans.store'), [
            'title' => '午前',
            'scheduled_on' => $date,
        ])->assertOk();

        $this->actingAs($user)->postJson(route('training-plans.store'), [
            'title' => '午後',
            'scheduled_on' => $date,
        ])->assertOk();

        Carbon::setTestNow('2026-07-07 12:00:00');

        $this->actingAs($user)
            ->get(route('training.index', ['date' => $date]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('plans', 2)
            );

        Carbon::setTestNow();
    }

    public function test_user_can_delete_a_plan_with_no_runs(): void
    {
        $user = User::factory()->create();
        $plan = TrainingPlan::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->deleteJson(route('training-plans.destroy', $plan))
            ->assertOk();

        $this->assertDatabaseMissing('training_plans', ['id' => $plan->id]);
    }

    public function test_user_cannot_delete_a_plan_that_has_runs(): void
    {
        $user = User::factory()->create();
        $plan = TrainingPlan::factory()->create(['user_id' => $user->id]);
        TrainingRun::factory()->create([
            'user_id' => $user->id,
            'training_plan_id' => $plan->id,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('training-plans.destroy', $plan))
            ->assertForbidden();

        $this->assertDatabaseHas('training_plans', ['id' => $plan->id]);
    }

    public function test_user_can_change_plan_status_from_draft_to_ready(): void
    {
        $user = User::factory()->create();
        $plan = TrainingPlan::factory()->create(['user_id' => $user->id]);
        TrainingPlanStep::factory()->forPlan($plan)->create();

        $this->actingAs($user)
            ->patchJson(route('training-plans.update', $plan), [
                'status' => TrainingPlanStatus::Ready->value,
            ])
            ->assertOk()
            ->assertJsonPath('plan.status', TrainingPlanStatus::Ready->value);
    }

    public function test_user_can_change_plan_status_from_ready_back_to_draft(): void
    {
        $user = User::factory()->create();
        $plan = TrainingPlan::factory()->ready()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->patchJson(route('training-plans.update', $plan), [
                'status' => TrainingPlanStatus::Draft->value,
            ])
            ->assertOk()
            ->assertJsonPath('plan.status', TrainingPlanStatus::Draft->value);
    }

    public function test_user_cannot_add_a_step_to_a_ready_plan(): void
    {
        $user = User::factory()->create();
        $plan = TrainingPlan::factory()->ready()->create(['user_id' => $user->id]);
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->postJson(route('training-plan-steps.store', $plan), [
                'exercise_id' => $exercise->id,
            ])
            ->assertForbidden();
    }

    public function test_user_cannot_update_a_step_on_a_ready_plan(): void
    {
        $user = User::factory()->create();
        $plan = TrainingPlan::factory()->ready()->create(['user_id' => $user->id]);
        $step = TrainingPlanStep::factory()->forPlan($plan)->create(['target_reps' => 10]);

        $this->actingAs($user)
            ->patchJson(route('training-plan-steps.update', [$plan, $step]), [
                'target_reps' => 20,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('training_plan_steps', [
            'id' => $step->id,
            'target_reps' => 10,
        ]);
    }

    public function test_user_can_view_plan_editor_with_steps_and_runs_as_lists(): void
    {
        $user = User::factory()->create();
        $plan = TrainingPlan::factory()->ready()->create(['user_id' => $user->id]);
        TrainingPlanStep::factory()->forPlan($plan)->create(['sort_order' => 1]);
        TrainingRun::factory()->create([
            'user_id' => $user->id,
            'training_plan_id' => $plan->id,
        ]);

        $this->actingAs($user)
            ->get(route('training-plans.show', $plan))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Training/PlanEdit')
                ->has('plan.steps', 1)
                ->has('plan.runs', 1)
                ->where('plan.runs.0.training_plan', null)
            );
    }
}
