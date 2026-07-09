<?php

namespace Tests\Feature;

use App\Models\RoutineItem;
use App\Models\Routine;
use App\Models\RoutineStep;
use App\Models\User;
use App\Models\Video;
use Database\Seeders\MatrixRowSeeder;
use Database\Seeders\MetricSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoutineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MatrixRowSeeder::class);
        $this->seed(MetricSeeder::class);
    }

    public function test_guests_cannot_access_routine_management(): void
    {
        $routine = Routine::factory()->create();
        $step = RoutineStep::factory()->forRoutine($routine)->create();

        $this->get(route('routines.index'))->assertRedirect(route('login'));
        $this->postJson(route('routines.store'), ['name' => '新規'])->assertUnauthorized();
        $this->get(route('routines.show', $routine))->assertRedirect(route('login'));
        $this->patchJson(route('routines.update', $routine), ['name' => '改ざん'])->assertUnauthorized();
        $this->deleteJson(route('routines.destroy', $routine))->assertUnauthorized();
        $this->postJson(route('routine-steps.store', $routine), ['routine_item_id' => $step->routine_item_id])->assertUnauthorized();
    }

    public function test_index_shows_only_the_authenticated_users_active_routines(): void
    {
        $user = User::factory()->create();
        Routine::factory()->create(['user_id' => $user->id, 'name' => '自分のルーティン', 'sort_order' => 1]);
        Routine::factory()->inactive()->create(['user_id' => $user->id, 'name' => '自分の非表示', 'sort_order' => 2]);
        Routine::factory()->create(['name' => '他人のルーティン']);

        $this->actingAs($user)
            ->get(route('routines.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Routines/Index')
                ->has('routines', 1)
                ->where('routines.0.name', '自分のルーティン')
            );
    }

    public function test_user_can_create_a_routine(): void
    {
        $user = User::factory()->create();
        Routine::factory()->create(['user_id' => $user->id, 'sort_order' => 2]);

        $response = $this->actingAs($user)->postJson(route('routines.store'), [
            'name' => '朝ルーティン',
            'description' => '毎朝の準備',
        ]);

        $response->assertOk()->assertJsonPath('routine.name', '朝ルーティン');

        $this->assertDatabaseHas('routines', [
            'user_id' => $user->id,
            'name' => '朝ルーティン',
            'sort_order' => 3,
        ]);
    }

    public function test_user_can_view_their_routine_editor(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id, 'name' => '編集対象']);
        RoutineStep::factory()->forRoutine($routine)->create(['sort_order' => 1]);

        $this->actingAs($user)
            ->get(route('routines.show', $routine))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Routines/Show')
                ->where('routine.name', '編集対象')
                ->has('routine.steps', 1)
                ->where('routine.steps', fn ($steps) => is_array($steps) && array_is_list($steps))
                ->has('routineItems')
                ->has('videos')
            );
    }

    public function test_user_cannot_view_another_users_routine(): void
    {
        $user = User::factory()->create();
        $otherRoutine = Routine::factory()->create();

        $this->actingAs($user)
            ->get(route('routines.show', $otherRoutine))
            ->assertForbidden();
    }

    public function test_user_can_update_their_routine(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id, 'name' => '旧名称']);

        $this->actingAs($user)
            ->patchJson(route('routines.update', $routine), ['name' => '新名称'])
            ->assertOk()
            ->assertJsonPath('routine.name', '新名称');

        $this->assertDatabaseHas('routines', ['id' => $routine->id, 'name' => '新名称']);
    }

    public function test_user_can_add_a_step_to_their_routine(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id]);
        $routineItem = RoutineItem::factory()->create(['user_id' => $user->id]);
        $video = Video::factory()->ready()->create([
            'user_id' => $user->id,
            'routine_item_id' => $routineItem->id,
        ]);

        $response = $this->actingAs($user)->postJson(route('routine-steps.store', $routine), [
            'routine_item_id' => $routineItem->id,
            'video_id' => $video->id,
            'purpose' => 'strength',
            'target_blocks' => 3,
            'target_amount' => 10,
            'amount_unit' => 'reps',
        ]);

        $response->assertOk()->assertJsonPath('step.routine_item_id', $routineItem->id);

        $this->assertDatabaseHas('routine_steps', [
            'routine_id' => $routine->id,
            'routine_item_id' => $routineItem->id,
            'video_id' => $video->id,
            'purpose' => 'strength',
            'sort_order' => 1,
        ]);
    }

    public function test_user_can_add_a_step_with_japanese_units_and_purpose_enum_payload(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id]);
        $routineItem = RoutineItem::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->postJson(route('routine-steps.store', $routine), [
            'routine_item_id' => $routineItem->id,
            'purpose' => 'practice',
            'target_blocks' => 3,
            'target_load' => 40,
            'load_unit' => 'kg',
            'target_amount' => 10,
            'amount_unit' => '回',
            'rest_seconds' => 60,
            'note' => 'テスト',
        ])->assertOk()->assertJsonPath('step.purpose', 'practice');

        $this->assertDatabaseHas('routine_steps', [
            'routine_id' => $routine->id,
            'routine_item_id' => $routineItem->id,
            'purpose' => 'practice',
            'amount_unit' => '回',
        ]);
    }

    public function test_user_can_reorder_their_routine_steps(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id]);
        $first = RoutineStep::factory()->forRoutine($routine)->create(['sort_order' => 1]);
        $second = RoutineStep::factory()->forRoutine($routine)->create(['sort_order' => 2]);

        $this->actingAs($user)->patch(route('routine-steps.reorder', $routine), [
            'ordered_ids' => [$second->id, $first->id],
        ])->assertRedirect();

        $this->assertSame(2, $first->refresh()->sort_order);
        $this->assertSame(1, $second->refresh()->sort_order);
    }

    public function test_user_can_delete_a_routine_step(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id]);
        $step = RoutineStep::factory()->forRoutine($routine)->create();

        $this->actingAs($user)
            ->deleteJson(route('routine-steps.destroy', [$routine, $step]))
            ->assertOk();

        $this->assertSoftDeleted('routine_steps', ['id' => $step->id]);
    }

    public function test_storing_a_step_rejects_another_users_routine_item_id(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id]);
        $otherRoutineItem = RoutineItem::factory()->create();

        $this->actingAs($user)
            ->postJson(route('routine-steps.store', $routine), [
                'routine_item_id' => $otherRoutineItem->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('routine_item_id');
    }

    public function test_storing_a_step_rejects_another_users_video_id(): void
    {
        $user = User::factory()->create();
        $routine = Routine::factory()->create(['user_id' => $user->id]);
        $routineItem = RoutineItem::factory()->create(['user_id' => $user->id]);
        $otherVideo = Video::factory()->ready()->create();

        $this->actingAs($user)
            ->postJson(route('routine-steps.store', $routine), [
                'routine_item_id' => $routineItem->id,
                'video_id' => $otherVideo->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('video_id');
    }
}
