<?php

namespace Tests\Feature;

use App\Models\LifeArea;
use App\Models\MatrixCell;
use App\Models\User;
use Database\Seeders\MatrixRowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LifeAreaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MatrixRowSeeder::class);
    }

    public function test_guests_cannot_access_life_area_management()
    {
        $lifeArea = LifeArea::factory()->create();

        $this->get(route('life-areas.index'))->assertRedirect(route('login'));
        $this->post(route('life-areas.store'))->assertRedirect(route('login'));
        $this->patch(route('life-areas.update', $lifeArea))->assertRedirect(route('login'));
        $this->patch(route('life-areas.reorder'))->assertRedirect(route('login'));
        $this->delete(route('life-areas.destroy', $lifeArea))->assertRedirect(route('login'));
        $this->patch(route('life-areas.restore', $lifeArea))->assertRedirect(route('login'));
    }

    public function test_index_shows_only_the_authenticated_users_life_areas()
    {
        $user = User::factory()->create();
        LifeArea::factory()->create(['user_id' => $user->id, 'name' => '自分の領域', 'sort_order' => 1]);
        LifeArea::factory()->inactive()->create(['user_id' => $user->id, 'name' => '自分の非表示領域', 'sort_order' => 2]);
        LifeArea::factory()->create(['name' => '他人の領域']);

        $this->actingAs($user)
            ->get(route('life-areas.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LifeAreas/Index')
                // 管理画面では非表示の領域も一覧に出す（再表示の導線のため）
                ->has('lifeAreas', 2)
                ->where('lifeAreas.0.name', '自分の領域')
                ->where('lifeAreas.1.name', '自分の非表示領域')
            );
    }

    public function test_user_can_create_a_life_area_with_cells_for_all_fixed_rows()
    {
        $user = User::factory()->create();
        LifeArea::factory()->create(['user_id' => $user->id, 'sort_order' => 3]);

        $response = $this->actingAs($user)->post(route('life-areas.store'), [
            'name' => '読書',
            'color' => 'moss',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $lifeArea = LifeArea::query()
            ->where('user_id', $user->id)
            ->where('name', '読書')
            ->firstOrFail();

        // sort_order は既存の最大値 + 1 でサーバー側が採番する
        $this->assertSame(4, $lifeArea->sort_order);
        $this->assertTrue($lifeArea->is_active);

        // 固定 3 行ぶんのセルが同時に生成される
        $this->assertSame(3, MatrixCell::query()->where('life_area_id', $lifeArea->id)->count());
    }

    public function test_life_area_name_and_color_are_validated()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('life-areas.store'), ['name' => '', 'color' => 'moss'])
            ->assertSessionHasErrors('name');

        $this->actingAs($user)
            ->post(route('life-areas.store'), ['name' => '読書', 'color' => '#ff0000'])
            ->assertSessionHasErrors('color');
    }

    public function test_user_can_update_their_own_life_area()
    {
        $user = User::factory()->create();
        $lifeArea = LifeArea::factory()->create(['user_id' => $user->id, 'color' => 'dawn']);

        $response = $this->actingAs($user)->patch(route('life-areas.update', $lifeArea), [
            'name' => '新しい名前',
            'color' => 'gilt',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('life_areas', [
            'id' => $lifeArea->id,
            'name' => '新しい名前',
            'color' => 'gilt',
        ]);
    }

    public function test_user_cannot_update_another_users_life_area()
    {
        $user = User::factory()->create();
        $otherArea = LifeArea::factory()->create(['name' => '他人の領域']);

        $this->actingAs($user)
            ->patch(route('life-areas.update', $otherArea), [
                'name' => '乗っ取り',
                'color' => 'gilt',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('life_areas', [
            'id' => $otherArea->id,
            'name' => '他人の領域',
        ]);
    }

    public function test_user_can_reorder_their_life_areas()
    {
        $user = User::factory()->create();
        $first = LifeArea::factory()->create(['user_id' => $user->id, 'sort_order' => 1]);
        $second = LifeArea::factory()->create(['user_id' => $user->id, 'sort_order' => 2]);

        $response = $this->actingAs($user)->patch(route('life-areas.reorder'), [
            'ordered_ids' => [$second->id, $first->id],
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(2, $first->refresh()->sort_order);
        $this->assertSame(1, $second->refresh()->sort_order);
    }

    public function test_reorder_does_not_affect_another_users_life_areas()
    {
        $user = User::factory()->create();
        $own = LifeArea::factory()->create(['user_id' => $user->id, 'sort_order' => 1]);
        $otherArea = LifeArea::factory()->create(['sort_order' => 5]);

        $this->actingAs($user)->patch(route('life-areas.reorder'), [
            'ordered_ids' => [$otherArea->id, $own->id],
        ])->assertRedirect();

        // 他人の領域は user_id スコープにより更新されない
        $this->assertSame(5, $otherArea->refresh()->sort_order);
        $this->assertSame(2, $own->refresh()->sort_order);
    }

    public function test_user_can_deactivate_and_reactivate_their_life_area()
    {
        $user = User::factory()->create();
        $lifeArea = LifeArea::factory()->create(['user_id' => $user->id]);
        $cell = MatrixCell::factory()->create([
            'user_id' => $user->id,
            'life_area_id' => $lifeArea->id,
        ]);

        $this->actingAs($user)->delete(route('life-areas.destroy', $lifeArea))->assertRedirect();

        $lifeArea->refresh();
        $this->assertFalse($lifeArea->is_active);
        // 非表示は soft delete ではない。セルデータも保持される
        $this->assertNull($lifeArea->deleted_at);
        $this->assertDatabaseHas('matrix_cells', ['id' => $cell->id]);

        $this->actingAs($user)->patch(route('life-areas.restore', $lifeArea))->assertRedirect();

        $this->assertTrue($lifeArea->refresh()->is_active);
    }

    public function test_user_cannot_deactivate_or_reactivate_another_users_life_area()
    {
        $user = User::factory()->create();
        $otherArea = LifeArea::factory()->create();

        $this->actingAs($user)->delete(route('life-areas.destroy', $otherArea))->assertForbidden();
        $this->actingAs($user)->patch(route('life-areas.restore', $otherArea))->assertForbidden();

        $this->assertTrue($otherArea->refresh()->is_active);
    }
}
