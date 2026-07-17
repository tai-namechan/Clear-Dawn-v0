<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\GoalMetric;
use App\Models\Metric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GoalTest extends TestCase
{
    use RefreshDatabase;

    private function createMetric(array $overrides = []): Metric
    {
        return Metric::query()->create(array_merge([
            'key' => 'weight',
            'label' => '体重',
            'unit' => 'kg',
            'value_type' => 'decimal',
            'sort_order' => 1,
        ], $overrides));
    }

    public function test_guests_cannot_access_goals(): void
    {
        $goal = Goal::factory()->create();

        $this->get(route('goals.index'))->assertRedirect(route('login'));
        $this->postJson(route('goals.store'), ['name' => 'x'])->assertUnauthorized();
        $this->patchJson(route('goals.update', $goal), ['name' => 'x', 'reason' => 'r'])->assertUnauthorized();
        $this->deleteJson(route('goals.destroy', $goal))->assertUnauthorized();
    }

    public function test_index_shows_only_the_authenticated_users_goal_tree(): void
    {
        $user = User::factory()->create();
        $parent = Goal::factory()->create(['user_id' => $user->id, 'name' => '競技復帰']);
        Goal::factory()->create([
            'user_id' => $user->id,
            'parent_goal_id' => $parent->id,
            'name' => '球速アップ',
        ]);
        Goal::factory()->create(['name' => '他人の目標']);

        $this->actingAs($user)
            ->get(route('goals.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Goals/Index')
                ->has('goals', 1)
                ->where('goals.0.name', '競技復帰')
                ->has('goals.0.children', 1)
                ->where('goals.0.children.0.name', '球速アップ'));
    }

    public function test_users_cannot_view_other_users_goals(): void
    {
        $user = User::factory()->create();
        $otherGoal = Goal::factory()->create();

        $this->actingAs($user)
            ->get(route('goals.show', $otherGoal))
            ->assertForbidden();
    }

    public function test_store_creates_a_goal(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('goals.store'), [
                'name' => '競技復帰',
                'why' => 'もう一度マウンドに立つ',
                'deadline' => '2027-03-31',
            ])
            ->assertOk()
            ->assertJsonPath('goal.name', '競技復帰');

        $this->assertDatabaseHas('goals', [
            'user_id' => $user->id,
            'name' => '競技復帰',
            'status' => 'active',
        ]);
    }

    public function test_update_requires_a_reason_and_records_a_change_log(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->create(['user_id' => $user->id, 'name' => '球速130km/h']);

        $this->actingAs($user)
            ->patchJson(route('goals.update', $goal), ['name' => '球速135km/h'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->actingAs($user)
            ->patchJson(route('goals.update', $goal), [
                'name' => '球速135km/h',
                'reason' => '進捗が想定より良いため上方修正',
            ])
            ->assertOk();

        $this->assertDatabaseHas('goal_change_logs', [
            'goal_id' => $goal->id,
            'reason' => '進捗が想定より良いため上方修正',
        ]);

        $log = $goal->changeLogs()->first();
        $this->assertSame(['from' => '球速130km/h', 'to' => '球速135km/h'], $log->changes['name']);
    }

    public function test_update_rejects_other_users_goal(): void
    {
        $user = User::factory()->create();
        $otherGoal = Goal::factory()->create();

        $this->actingAs($user)
            ->patchJson(route('goals.update', $otherGoal), ['name' => 'x', 'reason' => 'r'])
            ->assertForbidden();
    }

    public function test_destroy_soft_deletes_the_goal(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->deleteJson(route('goals.destroy', $goal))
            ->assertOk();

        $this->assertSoftDeleted('goals', ['id' => $goal->id]);
    }

    public function test_goal_metric_can_be_added_and_appears_in_change_log(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->create(['user_id' => $user->id]);
        $metric = $this->createMetric();

        $this->actingAs($user)
            ->postJson(route('goal-metrics.store', $goal), [
                'metric_id' => $metric->id,
                'baseline_value' => 62,
                'target_value' => 75,
                'direction' => 'increase',
            ])
            ->assertOk()
            ->assertJsonPath('goal_metric.metric.label', '体重');

        $this->assertDatabaseHas('goal_metrics', [
            'goal_id' => $goal->id,
            'metric_id' => $metric->id,
        ]);
        $this->assertDatabaseHas('goal_change_logs', ['goal_id' => $goal->id]);
    }

    public function test_goal_metric_update_requires_reason_and_logs_changes(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->create(['user_id' => $user->id]);
        $goalMetric = GoalMetric::factory()->create([
            'goal_id' => $goal->id,
            'metric_id' => $this->createMetric()->id,
            'target_value' => 70,
        ]);

        $this->actingAs($user)
            ->patchJson(route('goal-metrics.update', $goalMetric), ['target_value' => 75])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->actingAs($user)
            ->patchJson(route('goal-metrics.update', $goalMetric), [
                'target_value' => 75,
                'reason' => '増量ペースを引き上げ',
            ])
            ->assertOk();

        $this->assertSame('75.00', $goalMetric->fresh()->target_value);
        $this->assertDatabaseHas('goal_change_logs', [
            'goal_id' => $goal->id,
            'reason' => '増量ペースを引き上げ',
        ]);
    }

    public function test_goal_metric_can_be_removed(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->create(['user_id' => $user->id]);
        $goalMetric = GoalMetric::factory()->create([
            'goal_id' => $goal->id,
            'metric_id' => $this->createMetric()->id,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('goal-metrics.destroy', $goalMetric))
            ->assertOk();

        $this->assertDatabaseMissing('goal_metrics', ['id' => $goalMetric->id]);
    }

    public function test_goal_metric_endpoints_reject_other_users_goals(): void
    {
        $user = User::factory()->create();
        $otherGoal = Goal::factory()->create();
        $metric = $this->createMetric();

        $this->actingAs($user)
            ->postJson(route('goal-metrics.store', $otherGoal), ['metric_id' => $metric->id])
            ->assertForbidden();
    }

    public function test_show_displays_goal_with_metrics_programs_and_history(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->create(['user_id' => $user->id, 'name' => '競技復帰']);
        GoalMetric::factory()->create([
            'goal_id' => $goal->id,
            'metric_id' => $this->createMetric()->id,
        ]);

        $this->actingAs($user)
            ->get(route('goals.show', $goal))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Goals/Show')
                ->where('goal.name', '競技復帰')
                ->has('goal.goal_metrics', 1)
                ->has('availableMetrics'));
    }
}
