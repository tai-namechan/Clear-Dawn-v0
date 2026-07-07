<?php

namespace Tests\Feature;

use App\Enums\ActivityLogEventType;
use App\Enums\MatrixRowKey;
use App\Models\ActivityLog;
use App\Models\Exercise;
use App\Models\LifeArea;
use App\Models\MatrixCell;
use App\Models\MatrixCellItem;
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

class HistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MatrixRowSeeder::class);
        $this->seed(MetricSeeder::class);
    }

    private function createCurrentCellItem(User $user, string $title = 'マトリクス項目'): MatrixCellItem
    {
        $cell = MatrixCell::factory()
            ->forRow(MatrixRowKey::Current)
            ->create([
                'user_id' => $user->id,
                'life_area_id' => LifeArea::factory()->create(['user_id' => $user->id])->id,
            ]);

        return MatrixCellItem::factory()->create([
            'matrix_cell_id' => $cell->id,
            'title' => $title,
        ]);
    }

    private function completeTrainingRun(User $user): TrainingRun
    {
        $exercise = Exercise::factory()->create(['user_id' => $user->id]);
        $plan = TrainingPlan::factory()->ready()->create(['user_id' => $user->id]);
        TrainingPlanStep::factory()->forPlan($plan)->create(['exercise_id' => $exercise->id]);

        $this->actingAs($user)->postJson(route('training-runs.start', $plan))->assertOk();
        $run = TrainingRun::query()->where('user_id', $user->id)->firstOrFail();
        $this->actingAs($user)->postJson(route('training-runs.complete', $run))->assertOk();

        return $run->refresh();
    }

    public function test_guests_cannot_access_history(): void
    {
        $this->get(route('history.index'))->assertRedirect(route('login'));
    }

    public function test_index_shows_mixed_matrix_and_training_events_for_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $item = $this->createCurrentCellItem($user, '完了する項目');

        $this->actingAs($user)->patch(route('matrix-cell-items.toggle', $item))->assertRedirect();
        $this->completeTrainingRun($user);

        $this->actingAs($user)
            ->get(route('history.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('History/Index')
                ->has('history.data', 2)
                ->where('history.data', fn ($events) => collect($events)->contains(
                    fn (array $event): bool => $event['event_type'] === ActivityLogEventType::MatrixItemCompleted->value,
                ))
                ->where('history.data', fn ($events) => collect($events)->contains(
                    fn (array $event): bool => $event['event_type'] === ActivityLogEventType::TrainingRunCompleted->value,
                ))
            );
    }

    public function test_history_is_paginated(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 31; $i++) {
            ActivityLog::query()->create([
                'user_id' => $user->id,
                'event_type' => ActivityLogEventType::MatrixItemCompleted,
                'subject_type' => 'matrix_cell_item',
                'subject_id' => (string) \Illuminate\Support\Str::ulid(),
                'occurred_at' => now()->subMinutes($i),
            ]);
        }

        $this->actingAs($user)
            ->get(route('history.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('history.data', 30)
                ->where('history.meta.total', 31)
                ->where('history.meta.last_page', 2)
            );
    }

    public function test_history_can_be_filtered_by_event_type(): void
    {
        $user = User::factory()->create();
        $item = $this->createCurrentCellItem($user);
        $this->actingAs($user)->patch(route('matrix-cell-items.toggle', $item))->assertRedirect();
        $this->completeTrainingRun($user);

        $this->actingAs($user)
            ->get(route('history.index', ['event_type' => ActivityLogEventType::TrainingRunCompleted->value]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('history.data', 1)
                ->where('history.data.0.event_type', ActivityLogEventType::TrainingRunCompleted->value)
            );
    }

    public function test_history_can_be_filtered_by_date_range(): void
    {
        $user = User::factory()->create();

        ActivityLog::query()->create([
            'user_id' => $user->id,
            'event_type' => ActivityLogEventType::MatrixItemCompleted,
            'subject_type' => 'matrix_cell_item',
            'subject_id' => (string) \Illuminate\Support\Str::ulid(),
            'occurred_at' => Carbon::parse('2026-07-01 10:00:00'),
        ]);
        ActivityLog::query()->create([
            'user_id' => $user->id,
            'event_type' => ActivityLogEventType::MatrixItemCompleted,
            'subject_type' => 'matrix_cell_item',
            'subject_id' => (string) \Illuminate\Support\Str::ulid(),
            'occurred_at' => Carbon::parse('2026-07-15 10:00:00'),
        ]);

        $this->actingAs($user)
            ->get(route('history.index', [
                'from' => '2026-07-10',
                'to' => '2026-07-20',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('history.data', 1)
                ->where('history.data.0.occurred_at', fn (string $value): bool => str_starts_with($value, '2026-07-15'))
            );
    }

    public function test_history_does_not_include_other_users_events(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        ActivityLog::query()->create([
            'user_id' => $otherUser->id,
            'event_type' => ActivityLogEventType::MatrixItemCompleted,
            'subject_type' => 'matrix_cell_item',
            'subject_id' => (string) \Illuminate\Support\Str::ulid(),
            'occurred_at' => now(),
        ]);

        $item = $this->createCurrentCellItem($user, '自分の項目');
        $this->actingAs($user)->patch(route('matrix-cell-items.toggle', $item))->assertRedirect();

        $this->actingAs($user)
            ->get(route('history.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('history.data', 1)
                ->where('history.data.0.subject_summary.title', '自分の項目')
            );
    }
}
