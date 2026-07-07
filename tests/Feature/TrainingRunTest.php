<?php

namespace Tests\Feature;

use App\Enums\ActivityLogEventType;
use App\Enums\TrainingPlanStatus;
use App\Enums\TrainingRunStatus;
use App\Models\ActivityLog;
use App\Models\Exercise;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanStep;
use App\Models\TrainingRun;
use App\Models\TrainingRunStep;
use App\Models\User;
use App\Models\Video;
use Database\Seeders\MatrixRowSeeder;
use Database\Seeders\MetricSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainingRunTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MatrixRowSeeder::class);
        $this->seed(MetricSeeder::class);
    }

    /**
     * @return array{user: User, plan: TrainingPlan, exercise: Exercise}
     */
    private function readyPlanWithStep(User $user, string $exerciseName = 'ベンチプレス'): array
    {
        $exercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'name' => $exerciseName,
        ]);
        $plan = TrainingPlan::factory()->ready()->create(['user_id' => $user->id]);
        TrainingPlanStep::factory()->forPlan($plan)->create([
            'exercise_id' => $exercise->id,
            'sort_order' => 1,
        ]);

        return [
            'user' => $user,
            'plan' => $plan->load('steps'),
            'exercise' => $exercise,
        ];
    }

    public function test_guests_cannot_start_or_manage_training_runs(): void
    {
        $plan = TrainingPlan::factory()->ready()->create();
        $run = TrainingRun::factory()->create(['training_plan_id' => $plan->id]);
        $runStep = TrainingRunStep::factory()->create(['training_run_id' => $run->id]);

        $this->postJson(route('training-runs.start', $plan))->assertUnauthorized();
        $this->get(route('training-runs.show', $run))->assertRedirect(route('login'));
        $this->postJson(route('training-runs.complete', $run))->assertUnauthorized();
        $this->postJson(route('training-runs.abort', $run))->assertUnauthorized();
        $this->postJson(route('training-set-logs.store', $runStep), ['reps' => 10])->assertUnauthorized();
    }

    public function test_user_cannot_start_a_draft_plan(): void
    {
        $user = User::factory()->create();
        $plan = TrainingPlan::factory()->create(['user_id' => $user->id]);
        TrainingPlanStep::factory()->forPlan($plan)->create();

        $this->actingAs($user)
            ->postJson(route('training-runs.start', $plan))
            ->assertForbidden();
    }

    public function test_user_can_start_a_ready_plan(): void
    {
        $user = User::factory()->create();
        ['plan' => $plan] = $this->readyPlanWithStep($user);

        $response = $this->actingAs($user)->postJson(route('training-runs.start', $plan));

        $response->assertOk()->assertJsonPath('run.status', TrainingRunStatus::InProgress->value);

        $this->assertDatabaseHas('training_runs', [
            'user_id' => $user->id,
            'training_plan_id' => $plan->id,
            'status' => TrainingRunStatus::InProgress->value,
        ]);
    }

    public function test_start_snapshots_exercise_name_and_video_id(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'name' => 'デッドリフト',
        ]);
        $video = Video::factory()->ready()->create([
            'user_id' => $user->id,
            'exercise_id' => $exercise->id,
        ]);
        $plan = TrainingPlan::factory()->ready()->create(['user_id' => $user->id]);
        TrainingPlanStep::factory()->forPlan($plan)->create([
            'exercise_id' => $exercise->id,
            'video_id' => $video->id,
            'sort_order' => 1,
        ]);

        $this->actingAs($user)->postJson(route('training-runs.start', $plan))->assertOk();

        $this->assertDatabaseHas('training_run_steps', [
            'exercise_id' => $exercise->id,
            'exercise_name' => 'デッドリフト',
            'video_id' => $video->id,
        ]);
    }

    public function test_editing_a_plan_after_start_does_not_change_run_steps(): void
    {
        $user = User::factory()->create();
        ['plan' => $plan, 'exercise' => $exercise] = $this->readyPlanWithStep($user, 'スクワット');
        $planStep = $plan->steps->firstOrFail();

        $this->actingAs($user)->postJson(route('training-runs.start', $plan))->assertOk();

        $plan->update(['status' => TrainingPlanStatus::Draft]);
        $this->actingAs($user)->patchJson(route('training-plan-steps.update', [$plan, $planStep]), [
            'target_reps' => 99,
        ])->assertOk();

        $this->assertDatabaseHas('training_run_steps', [
            'exercise_id' => $exercise->id,
            'target_reps' => $planStep->target_reps,
        ]);
        $this->assertDatabaseMissing('training_run_steps', [
            'exercise_id' => $exercise->id,
            'target_reps' => 99,
        ]);
    }

    public function test_soft_deleting_an_exercise_after_start_preserves_exercise_name_on_run(): void
    {
        $user = User::factory()->create();
        ['plan' => $plan, 'exercise' => $exercise] = $this->readyPlanWithStep($user, '保持される名称');

        $this->actingAs($user)->postJson(route('training-runs.start', $plan))->assertOk();
        $this->actingAs($user)->deleteJson(route('exercises.destroy', $exercise))->assertOk();

        $this->assertDatabaseHas('training_run_steps', [
            'exercise_name' => '保持される名称',
        ]);
    }

    public function test_set_logs_receive_server_generated_set_numbers(): void
    {
        $user = User::factory()->create();
        ['plan' => $plan] = $this->readyPlanWithStep($user);

        $this->actingAs($user)->postJson(route('training-runs.start', $plan))->assertOk();

        $runStep = TrainingRunStep::query()->firstOrFail();

        $this->actingAs($user)->postJson(route('training-set-logs.store', $runStep), [
            'weight_kg' => 60,
            'reps' => 10,
        ])->assertOk()->assertJsonPath('set_log.set_number', 1);

        $this->actingAs($user)->postJson(route('training-set-logs.store', $runStep), [
            'weight_kg' => 65,
            'reps' => 8,
        ])->assertOk()->assertJsonPath('set_log.set_number', 2);
    }

    public function test_set_logs_can_only_be_recorded_while_run_is_in_progress(): void
    {
        $user = User::factory()->create();
        ['plan' => $plan] = $this->readyPlanWithStep($user);

        $this->actingAs($user)->postJson(route('training-runs.start', $plan))->assertOk();
        $run = TrainingRun::query()->firstOrFail();
        $runStep = $run->steps()->firstOrFail();

        $this->actingAs($user)->postJson(route('training-runs.complete', $run))->assertOk();

        $this->actingAs($user)->postJson(route('training-set-logs.store', $runStep), [
            'reps' => 10,
        ])->assertForbidden();
    }

    public function test_completing_a_run_creates_an_activity_log(): void
    {
        $user = User::factory()->create();
        ['plan' => $plan] = $this->readyPlanWithStep($user);

        $this->actingAs($user)->postJson(route('training-runs.start', $plan))->assertOk();
        $run = TrainingRun::query()->firstOrFail();

        $this->actingAs($user)->postJson(route('training-runs.complete', $run))->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'event_type' => ActivityLogEventType::TrainingRunCompleted->value,
            'subject_type' => 'training_run',
            'subject_id' => $run->id,
        ]);
    }

    public function test_aborting_a_run_does_not_create_an_activity_log(): void
    {
        $user = User::factory()->create();
        ['plan' => $plan] = $this->readyPlanWithStep($user);

        $this->actingAs($user)->postJson(route('training-runs.start', $plan))->assertOk();
        $run = TrainingRun::query()->firstOrFail();

        $this->actingAs($user)->postJson(route('training-runs.abort', $run))->assertOk();

        $this->assertDatabaseMissing('activity_logs', [
            'user_id' => $user->id,
            'subject_id' => $run->id,
        ]);
    }

    public function test_completing_a_run_is_idempotent_for_activity_log(): void
    {
        $user = User::factory()->create();
        ['plan' => $plan] = $this->readyPlanWithStep($user);

        $this->actingAs($user)->postJson(route('training-runs.start', $plan))->assertOk();
        $run = TrainingRun::query()->firstOrFail();

        $this->actingAs($user)->postJson(route('training-runs.complete', $run))->assertOk();
        $this->actingAs($user)->postJson(route('training-runs.complete', $run))->assertOk();

        $this->assertSame(1, ActivityLog::query()
            ->where('user_id', $user->id)
            ->where('event_type', ActivityLogEventType::TrainingRunCompleted)
            ->where('subject_id', $run->id)
            ->count());
    }

    public function test_user_cannot_access_another_users_training_run(): void
    {
        $user = User::factory()->create();
        $otherRun = TrainingRun::factory()->create();

        $this->actingAs($user)
            ->get(route('training-runs.show', $otherRun))
            ->assertForbidden();
    }
}
