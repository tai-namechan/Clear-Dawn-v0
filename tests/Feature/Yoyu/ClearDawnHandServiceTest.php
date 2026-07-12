<?php

namespace Tests\Feature\Yoyu;

use App\Domain\Yoyu\Services\ClearDawnHandService;
use App\Enums\MatrixRowKey;
use App\Models\LifeArea;
use App\Models\MatrixCell;
use App\Models\MatrixCellItem;
use App\Models\User;
use Database\Seeders\MatrixRowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClearDawnHandServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MatrixRowSeeder::class);
    }

    public function test_selects_current_row_item_by_life_area_then_sort_order(): void
    {
        $user = User::factory()->create();
        $areaB = LifeArea::factory()->create(['user_id' => $user->id, 'name' => 'B', 'sort_order' => 2]);
        $areaA = LifeArea::factory()->create(['user_id' => $user->id, 'name' => 'A', 'sort_order' => 1]);

        $cellB = MatrixCell::factory()->forRow(MatrixRowKey::Current)->create([
            'user_id' => $user->id,
            'life_area_id' => $areaB->id,
        ]);
        $cellA = MatrixCell::factory()->forRow(MatrixRowKey::Current)->create([
            'user_id' => $user->id,
            'life_area_id' => $areaA->id,
        ]);

        MatrixCellItem::factory()->create([
            'matrix_cell_id' => $cellB->id,
            'title' => 'later area',
            'sort_order' => 1,
            'is_completed' => false,
        ]);
        MatrixCellItem::factory()->create([
            'matrix_cell_id' => $cellA->id,
            'title' => 'second in A',
            'sort_order' => 2,
            'is_completed' => false,
        ]);
        MatrixCellItem::factory()->create([
            'matrix_cell_id' => $cellA->id,
            'title' => 'first in A',
            'sort_order' => 1,
            'is_completed' => false,
        ]);

        $monthly = MatrixCell::factory()->forRow(MatrixRowKey::Monthly)->create([
            'user_id' => $user->id,
            'life_area_id' => $areaA->id,
        ]);
        MatrixCellItem::factory()->create([
            'matrix_cell_id' => $monthly->id,
            'title' => 'monthly noise',
            'sort_order' => 0,
            'is_completed' => false,
        ]);

        $hand = app(ClearDawnHandService::class)->forUser($user);

        $this->assertNotNull($hand);
        $this->assertSame('first in A', $hand->title);
        $this->assertSame('A', $hand->lifeAreaName);
    }

    public function test_skips_completed_and_returns_null_when_none(): void
    {
        $user = User::factory()->create();
        $area = LifeArea::factory()->create(['user_id' => $user->id]);
        $cell = MatrixCell::factory()->forRow(MatrixRowKey::Current)->create([
            'user_id' => $user->id,
            'life_area_id' => $area->id,
        ]);
        MatrixCellItem::factory()->create([
            'matrix_cell_id' => $cell->id,
            'title' => 'done',
            'is_completed' => true,
        ]);

        $this->assertNull(app(ClearDawnHandService::class)->forUser($user));
    }

    public function test_never_returns_another_users_item(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $area = LifeArea::factory()->create(['user_id' => $other->id]);
        $cell = MatrixCell::factory()->forRow(MatrixRowKey::Current)->create([
            'user_id' => $other->id,
            'life_area_id' => $area->id,
        ]);
        MatrixCellItem::factory()->create([
            'matrix_cell_id' => $cell->id,
            'title' => 'other hand',
            'is_completed' => false,
        ]);

        $this->assertNull(app(ClearDawnHandService::class)->forUser($user));
    }

    public function test_selection_is_deterministic_across_calls(): void
    {
        $user = User::factory()->create();
        $area = LifeArea::factory()->create(['user_id' => $user->id, 'sort_order' => 1]);
        $cell = MatrixCell::factory()->forRow(MatrixRowKey::Current)->create([
            'user_id' => $user->id,
            'life_area_id' => $area->id,
        ]);
        MatrixCellItem::factory()->create([
            'matrix_cell_id' => $cell->id,
            'title' => 'alpha',
            'sort_order' => 1,
        ]);
        MatrixCellItem::factory()->create([
            'matrix_cell_id' => $cell->id,
            'title' => 'beta',
            'sort_order' => 2,
        ]);

        $service = app(ClearDawnHandService::class);
        $a = $service->forUser($user);
        $b = $service->forUser($user);

        $this->assertSame($a?->id, $b?->id);
        $this->assertSame('alpha', $a?->title);
    }
}
