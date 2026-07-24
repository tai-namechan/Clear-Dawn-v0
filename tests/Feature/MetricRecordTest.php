<?php

namespace Tests\Feature;

use App\Enums\RoutineItemCategory;
use App\Models\MealEntry;
use App\Models\Metric;
use App\Models\MetricRecord;
use App\Models\RoutineBlockLog;
use App\Models\RoutineItem;
use App\Models\RoutinePlan;
use App\Models\RoutinePlanStep;
use App\Models\RoutineSession;
use App\Models\User;
use Database\Seeders\MatrixRowSeeder;
use Database\Seeders\MetricSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MetricRecordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MatrixRowSeeder::class);
        $this->seed(MetricSeeder::class);
    }

    public function test_guests_cannot_manage_metric_records(): void
    {
        $record = MetricRecord::factory()->create();

        $this->get(route('records.index'))->assertRedirect(route('login'));
        $this->putJson(route('records.upsert-daily'), [
            'recorded_on' => '2026-07-07',
            'records' => [['metric_key' => 'weight', 'value' => 70]],
        ])->assertUnauthorized();
        $this->deleteJson(route('records.destroy', ['metric' => 'weight', 'metricRecord' => $record]))
            ->assertUnauthorized();
    }

    public function test_user_can_upsert_daily_metric_records(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson(route('records.upsert-daily'), [
            'recorded_on' => '2026-07-07',
            'records' => [
                ['metric_key' => 'weight', 'value' => 70.5],
            ],
        ])->assertOk();

        $this->actingAs($user)->putJson(route('records.upsert-daily'), [
            'recorded_on' => '2026-07-07',
            'records' => [
                ['metric_key' => 'weight', 'value' => 71.0],
            ],
        ])->assertOk();

        $metric = Metric::query()->where('key', 'weight')->firstOrFail();

        $this->assertSame(1, MetricRecord::query()
            ->where('user_id', $user->id)
            ->where('metric_id', $metric->id)
            ->whereDate('recorded_on', '2026-07-07')
            ->count());

        $this->assertDatabaseHas('metric_records', [
            'user_id' => $user->id,
            'metric_id' => $metric->id,
            'value' => '71.00',
        ]);
    }

    public function test_integer_metrics_reject_non_whole_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson(route('records.upsert-daily'), [
                'recorded_on' => '2026-07-07',
                'records' => [
                    ['metric_key' => 'sleep_minutes', 'value' => 420.5],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('records.0.value');
    }

    public function test_scale_metrics_reject_values_outside_one_to_five(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson(route('records.upsert-daily'), [
                'recorded_on' => '2026-07-07',
                'records' => [
                    ['metric_key' => 'pain_level', 'value' => 6],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('records.0.value');
    }

    public function test_daily_records_index_is_scoped_to_the_authenticated_user(): void
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

        $this->actingAs($user)
            ->get(route('records.index', ['date' => '2026-07-07']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Records/Index')
                ->has('mealTotals')
                ->has('mealChartPoints')
                ->has('conditionChartSeries.weight')
                ->has('conditionChartSeries.sleep_minutes')
                ->has('strengthChartPoints')
                ->where('chartFrom', '2026-07-01')
                ->where('chartTo', '2026-07-07')
                ->where('metrics', fn ($metrics) => collect($metrics)->contains(
                    fn (array $entry): bool => $entry['metric']['key'] === 'weight'
                        && $entry['record'] !== null
                        && $entry['record']['value'] === '70.00',
                ))
                ->where('conditionChartSeries.weight', fn ($points) => collect($points)->contains(
                    fn (array $point): bool => $point['date'] === '2026-07-07'
                        && $point['value'] === '70.00',
                ))
                ->where('conditionChartSeries.weight', fn ($points) => collect($points)->doesntContain(
                    fn (array $point): bool => $point['value'] === '99.00',
                ))
            );
    }

    public function test_records_index_hub_charts_are_scoped_and_windowed_to_seven_days(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $weight = Metric::query()->where('key', 'weight')->firstOrFail();

        MetricRecord::factory()->create([
            'user_id' => $user->id,
            'metric_id' => $weight->id,
            'recorded_on' => '2026-07-10',
            'value' => 71.5,
        ]);
        MetricRecord::factory()->create([
            'user_id' => $user->id,
            'metric_id' => $weight->id,
            'recorded_on' => '2026-06-01',
            'value' => 80,
        ]);
        MetricRecord::factory()->create([
            'user_id' => $other->id,
            'metric_id' => $weight->id,
            'recorded_on' => '2026-07-10',
            'value' => 99,
        ]);

        MealEntry::factory()->for($user)->create([
            'eaten_on' => '2026-07-08',
            'kcal' => 500,
            'protein_g' => 30,
            'fat_g' => 15,
            'carb_g' => 50,
        ]);
        MealEntry::factory()->for($user)->create([
            'eaten_on' => '2026-06-01',
            'kcal' => 900,
            'protein_g' => 40,
            'fat_g' => 20,
            'carb_g' => 80,
        ]);
        MealEntry::factory()->for($other)->create([
            'eaten_on' => '2026-07-08',
            'kcal' => 1200,
            'protein_g' => 60,
            'fat_g' => 40,
            'carb_g' => 100,
        ]);

        $mine = RoutineItem::factory()->create([
            'user_id' => $user->id,
            'category' => RoutineItemCategory::Strength,
            'name' => 'スクワット',
        ]);
        $theirs = RoutineItem::factory()->create([
            'user_id' => $other->id,
            'category' => RoutineItemCategory::Strength,
            'name' => '他ユーザー種目',
        ]);
        $this->createCompletedStrengthSessionForHttp($user, $mine, '2026-07-09', 100);
        $this->createCompletedStrengthSessionForHttp($other, $theirs, '2026-07-09', 200);

        $this->actingAs($user)
            ->get(route('records.index', ['date' => '2026-07-10']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Records/Index')
                ->where('chartFrom', '2026-07-04')
                ->where('chartTo', '2026-07-10')
                ->has('mealChartPoints', 1)
                ->where('mealChartPoints.0.date', '2026-07-08')
                ->where('mealChartPoints.0.kcal', 500)
                ->where('conditionChartSeries.weight', fn ($points) => collect($points)->count() === 1
                    && collect($points)->first()['date'] === '2026-07-10'
                    && collect($points)->first()['value'] === '71.50')
                ->has('strengthChartPoints', 1)
                ->where('strengthChartPoints.0.item_name', 'スクワット')
                ->where('strengthChartPoints.0.max_load_value', '100.00')
            );
    }

    public function test_condition_page_renders_and_is_scoped_to_user(): void
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

        $this->actingAs($user)
            ->get(route('records.condition', ['date' => '2026-07-07']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Records/Condition')
                ->has('chartSeries')
                ->has('checkin')
                ->where('metrics', fn ($metrics) => collect($metrics)->contains(
                    fn (array $entry): bool => $entry['metric']['key'] === 'weight'
                        && $entry['record'] !== null
                        && $entry['record']['value'] === '70.00',
                ))
                ->where('metrics', fn ($metrics) => ! collect($metrics)->contains(
                    fn (array $entry): bool => $entry['record'] !== null
                        && $entry['record']['value'] === '99.00',
                ))
            );
    }

    public function test_condition_page_shows_saved_checkin_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson(route('today.checkin.upsert'), [
                'checked_on' => '2026-07-07',
                'sleep_quality' => 8,
                'fatigue' => 2,
                'muscle_soreness' => 3,
                'stress' => 4,
                'mood' => 7,
                'readiness_self' => 9,
            ])
            ->assertOk();

        $this->actingAs($user)
            ->get(route('records.condition', ['date' => '2026-07-07']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Records/Condition')
                ->where('checkin.sleep_quality', 8)
                ->where('checkin.fatigue', 2)
                ->where('checkin.muscle_soreness', 3)
                ->where('checkin.stress', 4)
                ->where('checkin.mood', 7)
                ->where('checkin.readiness_self', 9));
    }

    public function test_user_can_delete_their_own_metric_record(): void
    {
        $user = User::factory()->create();
        $metric = Metric::query()->where('key', 'weight')->firstOrFail();
        $record = MetricRecord::factory()->create([
            'user_id' => $user->id,
            'metric_id' => $metric->id,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('records.destroy', ['metric' => 'weight', 'metricRecord' => $record]))
            ->assertOk();

        $this->assertDatabaseMissing('metric_records', ['id' => $record->id]);
    }

    public function test_user_cannot_delete_another_users_metric_record(): void
    {
        $user = User::factory()->create();
        $record = MetricRecord::factory()->create();

        $this->actingAs($user)
            ->deleteJson(route('records.destroy', ['metric' => 'weight', 'metricRecord' => $record]))
            ->assertForbidden();

        $this->assertDatabaseHas('metric_records', ['id' => $record->id]);
    }

    public function test_metric_route_binding_resolves_own_metric_over_other_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $ownMetric = Metric::query()->create([
            'user_id' => $user->id,
            'key' => 'grip_custom',
            'label' => '自分の握力',
            'unit' => 'kg',
            'value_type' => 'decimal',
            'sort_order' => 100,
        ]);
        Metric::query()->create([
            'user_id' => $other->id,
            'key' => 'grip_custom',
            'label' => '他人の握力',
            'unit' => 'kg',
            'value_type' => 'decimal',
            'sort_order' => 100,
        ]);

        $this->actingAs($user)
            ->get(route('records.show', ['metric' => 'grip_custom']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('metric.id', $ownMetric->id)
                ->where('metric.label', '自分の握力')
            );
    }

    public function test_metric_route_binding_does_not_resolve_other_users_private_metric(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Metric::query()->create([
            'user_id' => $other->id,
            'key' => 'private_only',
            'label' => '他人専用',
            'unit' => 'kg',
            'value_type' => 'decimal',
            'sort_order' => 100,
        ]);

        $this->actingAs($user)
            ->get(route('records.show', ['metric' => 'private_only']))
            ->assertNotFound();
    }

    public function test_unknown_metric_key_returns_not_found(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('records.show', ['metric' => 'unknown_metric']))
            ->assertNotFound();
    }

    public function test_visiting_records_ensures_missing_metrics_with_japanese_labels(): void
    {
        Metric::query()->delete();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('records.index', ['date' => '2026-07-07']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Records/Index')
                ->where('metrics', fn ($metrics) => collect($metrics)->contains(
                    fn (array $entry): bool => $entry['metric']['key'] === 'weight'
                        && $entry['metric']['label'] === '体重',
                ))
                ->where('metrics', fn ($metrics) => collect($metrics)->contains(
                    fn (array $entry): bool => $entry['metric']['key'] === 'sleep_minutes'
                        && $entry['metric']['label'] === '睡眠時間',
                ))
                ->where('metrics', fn ($metrics) => collect($metrics)->contains(
                    fn (array $entry): bool => $entry['metric']['key'] === 'pain_level'
                        && $entry['metric']['label'] === '痛みレベル',
                ))
                ->where('metrics', fn ($metrics) => collect($metrics)->contains(
                    fn (array $entry): bool => $entry['metric']['key'] === 'pitch_speed_max'
                        && $entry['metric']['label'] === '最高球速',
                ))
            );

        $this->assertDatabaseHas('metrics', ['key' => 'weight', 'label' => '体重']);
        $this->assertSame(6, Metric::query()->count());
    }

    public function test_visiting_condition_ensures_missing_metrics(): void
    {
        Metric::query()->delete();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('records.condition', ['date' => '2026-07-07']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Records/Condition')
                ->has('metrics', 6)
                ->where('metrics.0.metric.label', '体重')
            );
    }

    public function test_show_page_returns_weekly_chart_points_for_period_preset(): void
    {
        $user = User::factory()->create();
        $metric = Metric::query()->where('key', 'weight')->firstOrFail();

        MetricRecord::factory()->create([
            'user_id' => $user->id,
            'metric_id' => $metric->id,
            'recorded_on' => '2026-07-06',
            'value' => 70,
        ]);
        MetricRecord::factory()->create([
            'user_id' => $user->id,
            'metric_id' => $metric->id,
            'recorded_on' => '2026-07-08',
            'value' => 72,
        ]);
        MetricRecord::factory()->create([
            'user_id' => $user->id,
            'metric_id' => $metric->id,
            'recorded_on' => '2026-07-13',
            'value' => 80,
        ]);

        $this->actingAs($user)
            ->get(route('records.show', [
                'metric' => 'weight',
                'period' => 'month',
                'to' => '2026-07-31',
                'granularity' => 'week',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Records/Show')
                ->where('granularity', 'week')
                ->where('period', 'month')
                ->where('from', '2026-06-30')
                ->where('to', '2026-07-31')
                ->has('chartPoints', 2)
                ->where('chartPoints.0.date', '2026-07-06')
                ->where('chartPoints.0.value', '71.00')
            );
    }

    public function test_strength_page_returns_chart_points_scoped_to_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $mine = RoutineItem::factory()->create([
            'user_id' => $user->id,
            'category' => RoutineItemCategory::Strength,
            'name' => 'ベンチプレス',
        ]);
        $theirs = RoutineItem::factory()->create([
            'user_id' => $other->id,
            'category' => RoutineItemCategory::Strength,
            'name' => '他ユーザー種目',
        ]);

        $this->createCompletedStrengthSessionForHttp($user, $mine, '2026-07-10', 80);
        $this->createCompletedStrengthSessionForHttp($other, $theirs, '2026-07-10', 120);

        $this->actingAs($user)
            ->get(route('records.strength', [
                'period' => 'month',
                'to' => '2026-07-31',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Records/Strength')
                ->where('period', 'month')
                ->has('chartPoints', 1)
                ->where('chartPoints.0.item_name', 'ベンチプレス')
                ->where('chartPoints.0.max_load_value', '80.00')
            );
    }

    private function createCompletedStrengthSessionForHttp(
        User $user,
        RoutineItem $routineItem,
        string $date,
        float $loadValue,
    ): void {
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
    }
}
