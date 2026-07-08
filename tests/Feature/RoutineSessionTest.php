<?php

namespace Tests\Feature;

use App\Enums\ActivityLogEventType;
use App\Enums\RoutinePlanStatus;
use App\Enums\RoutineSessionStatus;
use App\Models\ActivityLog;
use App\Models\RoutineItem;
use App\Models\RoutinePlan;
use App\Models\RoutinePlanStep;
use App\Models\RoutineSession;
use App\Models\RoutineSessionStep;
use App\Models\User;
use App\Models\Video;
use Database\Seeders\MatrixRowSeeder;
use Database\Seeders\MetricSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoutineSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MatrixRowSeeder::class);
        $this->seed(MetricSeeder::class);
    }

    /**
     * @return array{user: User, plan: RoutinePlan, routineItem: RoutineItem}
     */
    private function readyPlanWithStep(User $user, string $itemName = 'ベンチプレス'): array
    {
        $routineItem = RoutineItem::factory()->create([
            'user_id' => $user->id,
            'name' => $itemName,
        ]);
        $plan = RoutinePlan::factory()->ready()->create(['user_id' => $user->id]);
        RoutinePlanStep::factory()->forPlan($plan)->create([
            'routine_item_id' => $routineItem->id,
            'sort_order' => 1,
        ]);

        return [
            'user' => $user,
            'plan' => $plan->load('steps'),
            'routineItem' => $routineItem,
        ];
    }

    public function test_guests_cannot_start_or_manage_routine_sessions(): void
    {
        $plan = RoutinePlan::factory()->ready()->create();
        $session = RoutineSession::factory()->create(['routine_plan_id' => $plan->id]);
        $sessionStep = RoutineSessionStep::factory()->create(['routine_session_id' => $session->id]);

        $this->postJson(route('routine-sessions.start', $plan))->assertUnauthorized();
        $this->get(route('routine-sessions.show', $session))->assertRedirect(route('login'));
        $this->postJson(route('routine-sessions.complete', $session))->assertUnauthorized();
        $this->postJson(route('routine-sessions.abort', $session))->assertUnauthorized();
        $this->postJson(route('routine-block-logs.store', $sessionStep), [
            'amount_value' => 10,
            'amount_unit' => 'reps',
        ])->assertUnauthorized();
    }

    public function test_user_cannot_start_a_draft_plan(): void
    {
        $user = User::factory()->create();
        $plan = RoutinePlan::factory()->create(['user_id' => $user->id]);
        RoutinePlanStep::factory()->forPlan($plan)->create();

        $this->actingAs($user)
            ->postJson(route('routine-sessions.start', $plan))
            ->assertForbidden();
    }

    public function test_user_can_start_a_ready_plan(): void
    {
        $user = User::factory()->create();
        ['plan' => $plan] = $this->readyPlanWithStep($user);

        $response = $this->actingAs($user)->postJson(route('routine-sessions.start', $plan));

        $response->assertOk()->assertJsonPath('session.status', RoutineSessionStatus::InProgress->value);

        $this->assertDatabaseHas('routine_sessions', [
            'user_id' => $user->id,
            'routine_plan_id' => $plan->id,
            'status' => RoutineSessionStatus::InProgress->value,
        ]);
    }

    public function test_start_snapshots_item_name_and_video_id(): void
    {
        $user = User::factory()->create();
        $routineItem = RoutineItem::factory()->create([
            'user_id' => $user->id,
            'name' => 'デッドリフト',
        ]);
        $video = Video::factory()->ready()->create([
            'user_id' => $user->id,
            'routine_item_id' => $routineItem->id,
        ]);
        $plan = RoutinePlan::factory()->ready()->create(['user_id' => $user->id]);
        RoutinePlanStep::factory()->forPlan($plan)->create([
            'routine_item_id' => $routineItem->id,
            'video_id' => $video->id,
            'sort_order' => 1,
        ]);

        $this->actingAs($user)->postJson(route('routine-sessions.start', $plan))->assertOk();

        $this->assertDatabaseHas('routine_session_steps', [
            'routine_item_id' => $routineItem->id,
            'item_name' => 'デッドリフト',
            'video_id' => $video->id,
        ]);
    }

    public function test_editing_a_plan_after_start_does_not_change_session_steps(): void
    {
        $user = User::factory()->create();
        ['plan' => $plan, 'routineItem' => $routineItem] = $this->readyPlanWithStep($user, 'スクワット');
        $planStep = $plan->steps->firstOrFail();

        $this->actingAs($user)->postJson(route('routine-sessions.start', $plan))->assertOk();

        $plan->update(['status' => RoutinePlanStatus::Draft]);
        $this->actingAs($user)->patchJson(route('routine-plan-steps.update', [$plan, $planStep]), [
            'target_amount' => 99,
        ])->assertOk();

        $this->assertDatabaseHas('routine_session_steps', [
            'routine_item_id' => $routineItem->id,
            'target_amount' => $planStep->target_amount,
        ]);
        $this->assertDatabaseMissing('routine_session_steps', [
            'routine_item_id' => $routineItem->id,
            'target_amount' => 99,
        ]);
    }

    public function test_soft_deleting_a_routine_item_after_start_preserves_item_name_on_session(): void
    {
        $user = User::factory()->create();
        ['plan' => $plan, 'routineItem' => $routineItem] = $this->readyPlanWithStep($user, '保持される名称');

        $this->actingAs($user)->postJson(route('routine-sessions.start', $plan))->assertOk();
        $this->actingAs($user)->deleteJson(route('routine-items.destroy', $routineItem))->assertOk();

        $this->assertDatabaseHas('routine_session_steps', [
            'item_name' => '保持される名称',
        ]);
    }

    public function test_block_logs_receive_server_generated_block_numbers(): void
    {
        $user = User::factory()->create();
        ['plan' => $plan] = $this->readyPlanWithStep($user);

        $this->actingAs($user)->postJson(route('routine-sessions.start', $plan))->assertOk();

        $sessionStep = RoutineSessionStep::query()->firstOrFail();

        $this->actingAs($user)->postJson(route('routine-block-logs.store', $sessionStep), [
            'load_value' => 60,
            'load_unit' => 'kg',
            'amount_value' => 10,
            'amount_unit' => 'reps',
        ])->assertOk()->assertJsonPath('block_log.block_number', 1);

        $this->actingAs($user)->postJson(route('routine-block-logs.store', $sessionStep), [
            'load_value' => 65,
            'load_unit' => 'kg',
            'amount_value' => 8,
            'amount_unit' => 'reps',
        ])->assertOk()->assertJsonPath('block_log.block_number', 2);
    }

    public function test_block_logs_can_only_be_recorded_while_session_is_in_progress(): void
    {
        $user = User::factory()->create();
        ['plan' => $plan] = $this->readyPlanWithStep($user);

        $this->actingAs($user)->postJson(route('routine-sessions.start', $plan))->assertOk();
        $session = RoutineSession::query()->firstOrFail();
        $sessionStep = $session->steps()->firstOrFail();

        $this->actingAs($user)->postJson(route('routine-sessions.complete', $session))->assertOk();

        $this->actingAs($user)->postJson(route('routine-block-logs.store', $sessionStep), [
            'amount_value' => 10,
            'amount_unit' => 'reps',
        ])->assertForbidden();
    }

    public function test_completing_a_session_creates_an_activity_log(): void
    {
        $user = User::factory()->create();
        ['plan' => $plan] = $this->readyPlanWithStep($user);

        $this->actingAs($user)->postJson(route('routine-sessions.start', $plan))->assertOk();
        $session = RoutineSession::query()->firstOrFail();

        $this->actingAs($user)->postJson(route('routine-sessions.complete', $session))->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'event_type' => ActivityLogEventType::RoutineSessionCompleted->value,
            'subject_type' => 'routine_session',
            'subject_id' => $session->id,
        ]);
    }

    public function test_aborting_a_session_does_not_create_an_activity_log(): void
    {
        $user = User::factory()->create();
        ['plan' => $plan] = $this->readyPlanWithStep($user);

        $this->actingAs($user)->postJson(route('routine-sessions.start', $plan))->assertOk();
        $session = RoutineSession::query()->firstOrFail();

        $this->actingAs($user)->postJson(route('routine-sessions.abort', $session))->assertOk();

        $this->assertDatabaseMissing('activity_logs', [
            'user_id' => $user->id,
            'subject_id' => $session->id,
        ]);
    }

    public function test_completing_a_session_is_idempotent_for_activity_log(): void
    {
        $user = User::factory()->create();
        ['plan' => $plan] = $this->readyPlanWithStep($user);

        $this->actingAs($user)->postJson(route('routine-sessions.start', $plan))->assertOk();
        $session = RoutineSession::query()->firstOrFail();

        $this->actingAs($user)->postJson(route('routine-sessions.complete', $session))->assertOk();
        $this->actingAs($user)->postJson(route('routine-sessions.complete', $session))->assertOk();

        $this->assertSame(1, ActivityLog::query()
            ->where('user_id', $user->id)
            ->where('event_type', ActivityLogEventType::RoutineSessionCompleted)
            ->where('subject_id', $session->id)
            ->count());
    }

    public function test_user_cannot_access_another_users_routine_session(): void
    {
        $user = User::factory()->create();
        $otherSession = RoutineSession::factory()->create();

        $this->actingAs($user)
            ->get(route('routine-sessions.show', $otherSession))
            ->assertForbidden();
    }

    public function test_routine_session_show_returns_list_shaped_steps_and_block_logs(): void
    {
        $user = User::factory()->create();
        ['plan' => $plan] = $this->readyPlanWithStep($user);

        $this->actingAs($user)->postJson(route('routine-sessions.start', $plan))->assertOk();
        $session = RoutineSession::query()->firstOrFail();
        $sessionStep = $session->steps()->firstOrFail();

        $this->actingAs($user)->postJson(route('routine-block-logs.store', $sessionStep), [
            'load_value' => 60,
            'load_unit' => 'kg',
            'amount_value' => 10,
            'amount_unit' => 'reps',
        ])->assertOk();

        $this->actingAs($user)
            ->get(route('routine-sessions.show', $session))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('session.steps', 1)
                ->has('session.steps.0.block_logs', 1)
            );
    }
}
