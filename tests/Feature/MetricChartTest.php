<?php

namespace Tests\Feature;

use App\Enums\ExerciseCategory;
use App\Models\Exercise;
use App\Models\Metric;
use App\Models\MetricRecord;
use App\Models\TrainingPlan;
use App\Models\TrainingPlanStep;
use App\Models\TrainingRun;
use App\Models\TrainingSetLog;
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

    public function test_strength_chart_excludes_aborted_runs(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'category' => ExerciseCategory::Strength,
            'name' => 'ベンチプレス',
        ]);

        $this->createCompletedStrengthRun($user, $exercise, '2026-07-07', 80);
        $this->createAbortedStrengthRun($user, $exercise, '2026-07-08', 100);

        $chart = app(GetStrengthChartQuery::class)->handle(
            $user,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31'),
        );

        $this->assertCount(1, $chart);
        $this->assertSame('2026-07-07', $chart[0]['date']);
        $this->assertSame('80.00', $chart[0]['max_weight_kg']);
    }

    public function test_strength_chart_aggregates_daily_max_weight_per_exercise(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create([
            'user_id' => $user->id,
            'category' => ExerciseCategory::Strength,
            'name' => 'スクワット',
        ]);

        $run = $this->createCompletedStrengthRun($user, $exercise, '2026-07-07', 60);
        $runStep = $run->steps()->firstOrFail();

        TrainingSetLog::factory()->create([
            'training_run_step_id' => $runStep->id,
            'set_number' => 2,
            'weight_kg' => 90,
            'reps' => 5,
        ]);

        $chart = app(GetStrengthChartQuery::class)->handle(
            $user,
            Carbon::parse('2026-07-01'),
            Carbon::parse('2026-07-31'),
        );

        $this->assertCount(1, $chart);
        $this->assertSame('スクワット', $chart[0]['exercise_name']);
        $this->assertSame('90.00', $chart[0]['max_weight_kg']);
    }

    private function createCompletedStrengthRun(
        User $user,
        Exercise $exercise,
        string $date,
        float $weightKg,
    ): TrainingRun {
        Carbon::setTestNow($date.' 10:00:00');

        $plan = TrainingPlan::factory()->ready()->create(['user_id' => $user->id]);
        TrainingPlanStep::factory()->forPlan($plan)->create(['exercise_id' => $exercise->id]);

        $this->actingAs($user)->postJson(route('training-runs.start', $plan))->assertOk();

        $run = TrainingRun::query()->where('user_id', $user->id)->latest('started_at')->firstOrFail();
        $runStep = $run->steps()->firstOrFail();

        TrainingSetLog::query()->where('training_run_step_id', $runStep->id)->delete();
        TrainingSetLog::factory()->create([
            'training_run_step_id' => $runStep->id,
            'set_number' => 1,
            'weight_kg' => $weightKg,
            'reps' => 5,
        ]);

        $this->actingAs($user)->postJson(route('training-runs.complete', $run))->assertOk();

        Carbon::setTestNow();

        return $run->refresh();
    }

    private function createAbortedStrengthRun(
        User $user,
        Exercise $exercise,
        string $date,
        float $weightKg,
    ): TrainingRun {
        Carbon::setTestNow($date.' 10:00:00');

        $plan = TrainingPlan::factory()->ready()->create(['user_id' => $user->id]);
        TrainingPlanStep::factory()->forPlan($plan)->create(['exercise_id' => $exercise->id]);

        $this->actingAs($user)->postJson(route('training-runs.start', $plan))->assertOk();

        $run = TrainingRun::query()->where('user_id', $user->id)->latest('started_at')->firstOrFail();
        $runStep = $run->steps()->firstOrFail();

        TrainingSetLog::query()->where('training_run_step_id', $runStep->id)->delete();
        TrainingSetLog::factory()->create([
            'training_run_step_id' => $runStep->id,
            'set_number' => 1,
            'weight_kg' => $weightKg,
            'reps' => 5,
        ]);

        $this->actingAs($user)->postJson(route('training-runs.abort', $run))->assertOk();

        Carbon::setTestNow();

        return $run->refresh();
    }
}
