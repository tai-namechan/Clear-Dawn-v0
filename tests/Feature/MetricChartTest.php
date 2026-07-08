<?php

namespace Tests\Feature;

use App\Enums\RoutineItemCategory;
use App\Models\Metric;
use App\Models\MetricRecord;
use App\Models\RoutineBlockLog;
use App\Models\RoutineItem;
use App\Models\RoutinePlan;
use App\Models\RoutinePlanStep;
use App\Models\RoutineSession;
use App\Models\User;
use App\Queries\GetMetricChartQuery;
use App\Queries\GetStrengthChartQuery;
use Database\Seeders\MatrixRowSeeder;
use Database\Seeders\MetricSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MetricChartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MatrixRowSeeder::class);
        $this->seed(MetricSeeder::class);
    }

    public function test_metric_chart_excludes_records_outside_the_date_range(): void
    {
        $user = User::factory()->create();
        $metric = Metric::query()->where('key', 'weight')->firstOrFail();

        MetricRecord::factory()->create([
            'user_id' => $user->id,
            'metric_id' => $metric->id,
            'recorded_on' => '2026-07-01',
            'value' => 68,
        ]);
        MetricRecord::factory()->create([
            'user_id' => $user->id,
            'metric_id' => $metric->id,
            'recorded_on' => '2026-07-10',
            'value' => 70,
        ]);
        MetricRecord::factory()->create([
            'user_id' => $user->id,
            'metric_id' => $metric->id,
            'recorded_on' => '2026-07-20',
            'value' => 72,
        ]);

        $chart = app(GetMetricChartQuery::class)->handle(
            $user,
            $metric,
            Carbon::parse('2026-07-05'),
            Carbon::parse('2026-07-15'),
        );

        $this->assertCount(1, $chart);
        $this->assertSame('2026-07-10', $chart->first()['date']);
        $this->assertSame('70.00', $chart->first()['value']);
    }

    public function test_metric_chart_excludes_other_users_records(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $metric = Metric::query()->where('key', 'weight')->firstOrFail();

        MetricRecord::factory()->create([
            'user_id' => $user->id,
            'metric_id' => $metric->id,
            'recorded_on' => '2026-07-07',
            'value' => 70,
        ]);
        MetricRecord::factory()->create([
            'user_id' => $otherUser->id,
            'metric_id' => $metric->id,
            'recorded_on' => '2026-07-07',
            'value' => 99,
        ]);

        $chart = app(GetMetricChartQuery::class)->handle(
            $user,
            $metric,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31'),
        );

        $this->assertCount(1, $chart);
        $this->assertSame('70.00', $chart->first()['value']);
    }

    public function test_strength_chart_excludes_aborted_sessions(): void
    {
        $user = User::factory()->create();
        $routineItem = RoutineItem::factory()->create([
            'user_id' => $user->id,
            'category' => RoutineItemCategory::Strength,
            'name' => 'ベンチプレス',
        ]);

        $this->createCompletedStrengthSession($user, $routineItem, '2026-07-07', 80);
        $this->createAbortedStrengthSession($user, $routineItem, '2026-07-08', 100);

        $chart = app(GetStrengthChartQuery::class)->handle(
            $user,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31'),
        );

        $this->assertCount(1, $chart);
        $this->assertSame('2026-07-07', $chart[0]['date']);
        $this->assertSame('80.00', $chart[0]['max_load_value']);
    }

    public function test_strength_chart_aggregates_daily_max_load_per_routine_item(): void
    {
        $user = User::factory()->create();
        $routineItem = RoutineItem::factory()->create([
            'user_id' => $user->id,
            'category' => RoutineItemCategory::Strength,
            'name' => 'スクワット',
        ]);

        $session = $this->createCompletedStrengthSession($user, $routineItem, '2026-07-07', 60);
        $sessionStep = $session->steps()->firstOrFail();

        RoutineBlockLog::factory()->create([
            'routine_session_step_id' => $sessionStep->id,
            'block_number' => 2,
            'load_value' => 90,
            'load_unit' => 'kg',
            'amount_value' => 5,
            'amount_unit' => 'reps',
        ]);

        $chart = app(GetStrengthChartQuery::class)->handle(
            $user,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31'),
        );

        $this->assertCount(1, $chart);
        $this->assertSame('スクワット', $chart[0]['item_name']);
        $this->assertSame('90.00', $chart[0]['max_load_value']);
    }

    private function createCompletedStrengthSession(
        User $user,
        RoutineItem $routineItem,
        string $date,
        float $loadValue,
    ): RoutineSession {
        Carbon::setTestNow($date.' 10:00:00');

        $plan = RoutinePlan::factory()->ready()->create(['user_id' => $user->id]);
        RoutinePlanStep::factory()->forPlan($plan)->create(['routine_item_id' => $routineItem->id]);

        $this->actingAs($user)->postJson(route('routine-sessions.start', $plan))->assertOk();

        $session = RoutineSession::query()->where('user_id', $user->id)->latest('started_at')->firstOrFail();
        $sessionStep = $session->steps()->firstOrFail();

        RoutineBlockLog::query()->where('routine_session_step_id', $sessionStep->id)->delete();
        RoutineBlockLog::factory()->create([
            'routine_session_step_id' => $sessionStep->id,
            'block_number' => 1,
            'load_value' => $loadValue,
            'load_unit' => 'kg',
            'amount_value' => 5,
            'amount_unit' => 'reps',
        ]);

        $this->actingAs($user)->postJson(route('routine-sessions.complete', $session))->assertOk();

        Carbon::setTestNow();

        return $session->refresh();
    }

    private function createAbortedStrengthSession(
        User $user,
        RoutineItem $routineItem,
        string $date,
        float $loadValue,
    ): RoutineSession {
        Carbon::setTestNow($date.' 10:00:00');

        $plan = RoutinePlan::factory()->ready()->create(['user_id' => $user->id]);
        RoutinePlanStep::factory()->forPlan($plan)->create(['routine_item_id' => $routineItem->id]);

        $this->actingAs($user)->postJson(route('routine-sessions.start', $plan))->assertOk();

        $session = RoutineSession::query()->where('user_id', $user->id)->latest('started_at')->firstOrFail();
        $sessionStep = $session->steps()->firstOrFail();

        RoutineBlockLog::query()->where('routine_session_step_id', $sessionStep->id)->delete();
        RoutineBlockLog::factory()->create([
            'routine_session_step_id' => $sessionStep->id,
            'block_number' => 1,
            'load_value' => $loadValue,
            'load_unit' => 'kg',
            'amount_value' => 5,
            'amount_unit' => 'reps',
        ]);

        $this->actingAs($user)->postJson(route('routine-sessions.abort', $session))->assertOk();

        Carbon::setTestNow();

        return $session->refresh();
    }
}
