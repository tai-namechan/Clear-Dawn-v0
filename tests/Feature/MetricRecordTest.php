<?php

namespace Tests\Feature;

use App\Models\Metric;
use App\Models\MetricRecord;
use App\Models\User;
use Database\Seeders\MatrixRowSeeder;
use Database\Seeders\MetricSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                ->where('metrics', fn ($metrics) => collect($metrics)->contains(
                    fn (array $entry): bool => $entry['metric']['key'] === 'weight'
                        && $entry['record'] !== null
                        && $entry['record']['value'] === '70.00',
                ))
            );
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

    public function test_unknown_metric_key_returns_not_found(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('records.show', ['metric' => 'unknown_metric']))
            ->assertNotFound();
    }
}
