<?php

namespace Tests\Feature;

use App\Models\LifeArea;
use App\Models\MatrixCell;
use App\Models\MatrixCellItem;
use App\Models\MatrixRow;
use App\Models\User;
use Database\Seeders\MatrixRowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MatrixRowSeeder::class);
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_first_visit_creates_default_life_areas_and_matrix_cells()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $areas = LifeArea::query()
            ->where('user_id', $user->id)
            ->orderBy('sort_order')
            ->get();

        $this->assertSame(['仕事', '野球', 'バイオリン', 'プライベート'], $areas->pluck('name')->all());
        $this->assertTrue($areas->every(fn (LifeArea $area) => $area->is_active));

        // 領域 4 × 固定 3 行 = 12 セル
        $this->assertSame(12, MatrixCell::query()->where('user_id', $user->id)->count());
    }

    public function test_second_visit_does_not_duplicate_default_life_areas()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $this->assertSame(4, LifeArea::query()->where('user_id', $user->id)->count());
        $this->assertSame(12, MatrixCell::query()->where('user_id', $user->id)->count());
    }

    public function test_initialization_is_skipped_when_the_user_already_has_a_life_area()
    {
        $user = User::factory()->create();
        LifeArea::factory()->inactive()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        // 非表示の領域が 1 つでも存在すれば初期生成しない
        $this->assertSame(1, LifeArea::query()->where('user_id', $user->id)->count());
    }

    public function test_dashboard_shows_only_the_authenticated_users_data()
    {
        $user = User::factory()->create();
        $area = LifeArea::factory()->create(['user_id' => $user->id, 'name' => '自分の領域']);
        $cell = MatrixCell::factory()->create([
            'user_id' => $user->id,
            'life_area_id' => $area->id,
        ]);
        MatrixCellItem::factory()->create([
            'matrix_cell_id' => $cell->id,
            'title' => '自分の項目',
        ]);

        $otherUser = User::factory()->create();
        $otherArea = LifeArea::factory()->create(['user_id' => $otherUser->id, 'name' => '他人の領域']);
        $otherCell = MatrixCell::factory()->create([
            'user_id' => $otherUser->id,
            'life_area_id' => $otherArea->id,
        ]);
        MatrixCellItem::factory()->create([
            'matrix_cell_id' => $otherCell->id,
            'title' => '他人の項目',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('areas', 1)
                ->where('areas.0.name', '自分の領域')
                ->has('rows', 3)
                ->where('rows.0.cells.0.items', [])
            );
    }

    public function test_dashboard_does_not_show_inactive_life_areas()
    {
        $user = User::factory()->create();
        LifeArea::factory()->create(['user_id' => $user->id, 'name' => '表示中', 'sort_order' => 1]);
        LifeArea::factory()->inactive()->create(['user_id' => $user->id, 'name' => '非表示', 'sort_order' => 2]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('areas', 1)
                ->where('areas.0.name', '表示中')
            );
    }

    public function test_dashboard_does_not_show_soft_deleted_items()
    {
        $user = User::factory()->create();
        $area = LifeArea::factory()->create(['user_id' => $user->id, 'sort_order' => 1]);
        $cell = MatrixCell::factory()->create([
            'user_id' => $user->id,
            'life_area_id' => $area->id,
            'matrix_row_id' => MatrixRow::query()->where('key', 'current')->firstOrFail()->id,
        ]);
        MatrixCellItem::factory()->create(['matrix_cell_id' => $cell->id, 'title' => '残る項目', 'sort_order' => 1]);
        MatrixCellItem::factory()->create(['matrix_cell_id' => $cell->id, 'title' => '消えた項目', 'sort_order' => 2])->delete();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                // rows は sort_order 順（monthly, current, future）で current は 2 番目
                ->has('rows.1.cells.0.items', 1)
                ->where('rows.1.cells.0.items.0.title', '残る項目')
            );
    }
}
