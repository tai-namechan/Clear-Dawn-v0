<?php

namespace Tests\Feature;

use App\Enums\RoutinePlanStatus;
use App\Models\Routine;
use App\Models\RoutineItem;
use App\Models\RoutinePlan;
use App\Models\RoutinePlanStep;
use App\Models\RoutineSession;
use App\Models\RoutineStep;
use App\Models\User;
use Database\Seeders\MatrixRowSeeder;
use Database\Seeders\MetricSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoutinePlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MatrixRowSeeder::class);
        $this->seed(MetricSeeder::class);
    }

    public function test_guests_cannot_access_routine_plans(): void
    {
        $plan = RoutinePlan::factory()->create();

        $this->get(route('today.index'))->assertRedirect(route('login'));
        $this->postJson(route('routine-plans.store'), [
            'title' => '新規',
            'scheduled_on' => now()->toDateString(),
        ])->assertUnauthorized();
        $this->get(route('routine-plans.show', $plan))->assertRedirect(route('login'));
        $this->deleteJson(route('routine-plans.destroy', $plan))->assertUnauthorized();
    }

    public function test_user_can_create_an_empty_routine_plan(): void
    {
        $user = User::factory()->create();
        $date = '2026-07-07';

        $response = $this->actingAs($user)->postJson(route('routine-plans.store'), [
            'title' => '空のプラン',
            'scheduled_on' => $date,
        ]);

        $response->assertOk()
            ->assertJsonPath('plan.title', '空のプラン')
            ->assertJsonPath('plan.status', RoutinePlanStatus::Draft->value);

        $plan = RoutinePlan::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(0, $plan->steps()->count());
        $this->assertSame($date, $plan->scheduled_on->toDateString());
    }

    public function test_user_can_create_a_plan_with_steps_snapshotted_from_a_routine(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id]);
        $step = RoutineStep::factory()->forRoutine($routine)->create([
            'sort_order' => 1,
            'target_blocks' => 3,
            'target_amount' => 10,
            'amount_unit' => 'reps',
        ]);

        $response = $this->actingAs($user)->postJson(route('routine-plans.store'), [
            'title' => 'ルーティンから作成',
            'scheduled_on' => '2026-07-07',
            'routine_id' => $routine->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('plan.routine_id', $routine->id)
            ->assertJsonPath('plan.status', RoutinePlanStatus::Ready->value);

        $plan = RoutinePlan::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(1, $plan->steps()->count());
        $this->assertDatabaseHas('routine_plan_steps', [
            'routine_plan_id' => $plan->id,
            'routine_item_id' => $step->routine_item_id,
            'target_blocks' => 3,
            'target_amount' => 10,
            'amount_unit' => 'reps',
            'sort_order' => 1,
        ]);
    }

    public function test_editing_a_routine_after_plan_creation_does_not_change_plan_steps(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id]);
        $step = RoutineStep::factory()->forRoutine($routine)->create([
            'target_amount' => 10,
            'amount_unit' => 'reps',
            'sort_order' => 1,
        ]);

        $this->actingAs($user)->postJson(route('routine-plans.store'), [
            'title' => 'スナップショット',
            'scheduled_on' => '2026-07-07',
            'routine_id' => $routine->id,
        ])->assertOk();

        $plan = RoutinePlan::query()->where('user_id', $user->id)->firstOrFail();
        $planStepId = $plan->steps()->firstOrFail()->id;

        $this->actingAs($user)->patchJson(route('routine-steps.update', [$routine, $step]), [
            'target_amount' => 20,
        ])->assertOk();

        $this->assertDatabaseHas('routine_plan_steps', [
            'id' => $planStepId,
            'target_amount' => 10,
        ]);
    }

    public function test_user_can_create_multiple_plans_for_the_same_day(): void
    {
        $user = User::factory()->create();
        $date = '2026-07-07';

        $this->actingAs($user)->postJson(route('routine-plans.store'), [
            'title' => '午前',
            'scheduled_on' => $date,
        ])->assertOk();

        $this->actingAs($user)->postJson(route('routine-plans.store'), [
            'title' => '午後',
            'scheduled_on' => $date,
        ])->assertOk();

        Carbon::setTestNow('2026-07-07 12:00:00');

        $this->actingAs($user)
            ->get(route('today.index', ['date' => $date]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('plans', 2)
            );

        Carbon::setTestNow();
    }

    public function test_user_can_delete_a_plan_with_no_sessions(): void
    {
        $user = User::factory()->create();
        $plan = RoutinePlan::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->deleteJson(route('routine-plans.destroy', $plan))
            ->assertOk();

        $this->assertDatabaseMissing('routine_plans', ['id' => $plan->id]);
    }

    public function test_user_cannot_delete_a_plan_that_has_sessions(): void
    {
        $user = User::factory()->create();
        $plan = RoutinePlan::factory()->create(['user_id' => $user->id]);
        RoutineSession::factory()->create([
            'user_id' => $user->id,
            'routine_plan_id' => $plan->id,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('routine-plans.destroy', $plan))
            ->assertForbidden();

        $this->assertDatabaseHas('routine_plans', ['id' => $plan->id]);
    }

    public function test_user_can_change_plan_status_from_draft_to_ready(): void
    {
        $user = User::factory()->create();
        $plan = RoutinePlan::factory()->create(['user_id' => $user->id]);
        RoutinePlanStep::factory()->forPlan($plan)->create();

        $this->actingAs($user)
            ->patchJson(route('routine-plans.update', $plan), [
                'status' => RoutinePlanStatus::Ready->value,
            ])
            ->assertOk()
            ->assertJsonPath('plan.status', RoutinePlanStatus::Ready->value);
    }

    public function test_user_can_change_plan_status_from_ready_back_to_draft(): void
    {
        $user = User::factory()->create();
        $plan = RoutinePlan::factory()->ready()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->patchJson(route('routine-plans.update', $plan), [
                'status' => RoutinePlanStatus::Draft->value,
            ])
            ->assertOk()
            ->assertJsonPath('plan.status', RoutinePlanStatus::Draft->value);
    }

    public function test_user_cannot_add_a_step_to_a_ready_plan(): void
    {
        $user = User::factory()->create();
        $plan = RoutinePlan::factory()->ready()->create(['user_id' => $user->id]);
        $routineItem = RoutineItem::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->postJson(route('routine-plan-steps.store', $plan), [
                'routine_item_id' => $routineItem->id,
            ])
            ->assertForbidden();
    }

    public function test_user_cannot_update_a_step_on_a_ready_plan(): void
    {
        $user = User::factory()->create();
        $plan = RoutinePlan::factory()->ready()->create(['user_id' => $user->id]);
        $step = RoutinePlanStep::factory()->forPlan($plan)->create([
            'target_amount' => 10,
            'amount_unit' => 'reps',
        ]);

        $this->actingAs($user)
            ->patchJson(route('routine-plan-steps.update', [$plan, $step]), [
                'target_amount' => 20,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('routine_plan_steps', [
            'id' => $step->id,
            'target_amount' => 10,
        ]);
    }

    public function test_user_can_reorder_their_routine_plan_steps(): void
    {
        $user = User::factory()->create();
        $plan = RoutinePlan::factory()->create(['user_id' => $user->id]);
        $first = RoutinePlanStep::factory()->forPlan($plan)->create(['sort_order' => 1]);
        $second = RoutinePlanStep::factory()->forPlan($plan)->create(['sort_order' => 2]);

        $this->actingAs($user)->patch(route('routine-plan-steps.reorder', $plan), [
            'ordered_ids' => [$second->id, $first->id],
        ])->assertRedirect();

        $this->assertSame(2, $first->refresh()->sort_order);
        $this->assertSame(1, $second->refresh()->sort_order);
    }
}
