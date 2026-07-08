<?php

namespace Tests\Feature;

use App\Models\RoutineItem;
use App\Models\User;
use App\Models\Video;
use Database\Seeders\MatrixRowSeeder;
use Database\Seeders\MetricSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoutineItemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MatrixRowSeeder::class);
        $this->seed(MetricSeeder::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function routineItemPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'ベンチプレス',
            'category' => 'strength',
            'tracking_type' => 'weight_reps',
        ], $overrides);
    }

    public function test_guests_cannot_access_routine_item_management(): void
    {
        $routineItem = RoutineItem::factory()->create();

        $this->get(route('routine-items.index'))->assertRedirect(route('login'));
        $this->postJson(route('routine-items.store'), $this->routineItemPayload())->assertUnauthorized();
        $this->patchJson(route('routine-items.update', $routineItem), ['name' => '改ざん'])->assertUnauthorized();
        $this->deleteJson(route('routine-items.destroy', $routineItem))->assertUnauthorized();
    }

    public function test_index_shows_only_the_authenticated_users_active_routine_items(): void
    {
        $user = User::factory()->create();
        RoutineItem::factory()->create(['user_id' => $user->id, 'name' => '自分の種目']);
        RoutineItem::factory()->inactive()->create(['user_id' => $user->id, 'name' => '自分の非表示種目']);
        RoutineItem::factory()->create(['name' => '他人の種目']);

        $this->actingAs($user)
            ->get(route('routine-items.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('RoutineItems/Index')
                ->has('routineItems', 1)
                ->where('routineItems.0.name', '自分の種目')
            );
    }

    public function test_user_can_create_a_routine_item(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('routine-items.store'), $this->routineItemPayload([
            'name' => 'スクワット',
            'note' => 'フォーム確認',
        ]));

        $response->assertOk()
            ->assertJsonPath('routine_item.name', 'スクワット')
            ->assertJsonPath('routine_item.is_active', true);

        $this->assertDatabaseHas('routine_items', [
            'user_id' => $user->id,
            'name' => 'スクワット',
            'note' => 'フォーム確認',
            'is_active' => true,
        ]);
    }

    public function test_user_can_update_their_own_routine_item(): void
    {
        $user = User::factory()->create();
        $routineItem = RoutineItem::factory()->create(['user_id' => $user->id, 'name' => '旧名称']);

        $response = $this->actingAs($user)->patchJson(route('routine-items.update', $routineItem), [
            'name' => '新名称',
            'is_active' => false,
        ]);

        $response->assertOk()->assertJsonPath('routine_item.name', '新名称');

        $this->assertDatabaseHas('routine_items', [
            'id' => $routineItem->id,
            'name' => '新名称',
            'is_active' => false,
        ]);
    }

    public function test_user_cannot_update_another_users_routine_item(): void
    {
        $user = User::factory()->create();
        $otherRoutineItem = RoutineItem::factory()->create(['name' => '他人の種目']);

        $this->actingAs($user)
            ->patchJson(route('routine-items.update', $otherRoutineItem), ['name' => '乗っ取り'])
            ->assertForbidden();

        $this->assertDatabaseHas('routine_items', [
            'id' => $otherRoutineItem->id,
            'name' => '他人の種目',
        ]);
    }

    public function test_user_can_soft_delete_their_own_routine_item(): void
    {
        $user = User::factory()->create();
        $routineItem = RoutineItem::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->deleteJson(route('routine-items.destroy', $routineItem))
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertSoftDeleted('routine_items', ['id' => $routineItem->id]);
    }

    public function test_user_cannot_delete_another_users_routine_item(): void
    {
        $user = User::factory()->create();
        $otherRoutineItem = RoutineItem::factory()->create();

        $this->actingAs($user)
            ->deleteJson(route('routine-items.destroy', $otherRoutineItem))
            ->assertForbidden();

        $this->assertDatabaseHas('routine_items', ['id' => $otherRoutineItem->id, 'deleted_at' => null]);
    }

    public function test_deactivated_routine_items_are_excluded_from_index(): void
    {
        $user = User::factory()->create();
        $active = RoutineItem::factory()->create(['user_id' => $user->id, 'name' => '有効な種目']);
        $inactive = RoutineItem::factory()->create(['user_id' => $user->id, 'name' => '無効化する種目']);

        $this->actingAs($user)->patchJson(route('routine-items.update', $inactive), [
            'is_active' => false,
        ])->assertOk();

        $this->actingAs($user)
            ->get(route('routine-items.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('routineItems', 1)
                ->where('routineItems.0.id', $active->id)
            );
    }

    public function test_index_counts_videos_scoped_to_each_routine_item(): void
    {
        $user = User::factory()->create();
        $bench = RoutineItem::factory()->create(['user_id' => $user->id, 'name' => 'ベンチ']);
        $squat = RoutineItem::factory()->create(['user_id' => $user->id, 'name' => 'スクワット']);

        Video::factory()->ready()->count(2)->create([
            'user_id' => $user->id,
            'routine_item_id' => $bench->id,
        ]);
        Video::factory()->ready()->create([
            'user_id' => $user->id,
            'routine_item_id' => $squat->id,
        ]);
        Video::factory()->ready()->create([
            'user_id' => $user->id,
            'routine_item_id' => null,
        ]);

        $this->actingAs($user)
            ->get(route('routine-items.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('routineItems', fn ($routineItems) => collect($routineItems)->contains(
                    fn (array $item): bool => $item['id'] === $bench->id && $item['videos_count'] === 2,
                ))
                ->where('routineItems', fn ($routineItems) => collect($routineItems)->contains(
                    fn (array $item): bool => $item['id'] === $squat->id && $item['videos_count'] === 1,
                ))
            );
    }
}
