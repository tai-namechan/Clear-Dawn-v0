<?php

namespace Tests\Feature;

use App\Enums\MatrixRowKey;
use App\Models\LifeArea;
use App\Models\MatrixCell;
use App\Models\MatrixCellItem;
use App\Models\User;
use Database\Seeders\MatrixRowSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatrixCellItemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MatrixRowSeeder::class);
    }

    /**
     * 指定ユーザー・指定行のセルを 1 つ作る。
     */
    private function createCellFor(User $user, MatrixRowKey $rowKey = MatrixRowKey::Current): MatrixCell
    {
        return MatrixCell::factory()
            ->forRow($rowKey)
            ->create([
                'user_id' => $user->id,
                'life_area_id' => LifeArea::factory()->create(['user_id' => $user->id])->id,
            ]);
    }

    public function test_guests_cannot_manage_matrix_cell_items()
    {
        $cell = $this->createCellFor(User::factory()->create());
        $item = MatrixCellItem::factory()->create(['matrix_cell_id' => $cell->id]);

        $this->post(route('matrix-cell-items.store', $cell))->assertRedirect(route('login'));
        $this->patch(route('matrix-cell-items.update', $item))->assertRedirect(route('login'));
        $this->patch(route('matrix-cell-items.toggle', $item))->assertRedirect(route('login'));
        $this->delete(route('matrix-cell-items.destroy', $item))->assertRedirect(route('login'));
    }

    public function test_user_can_add_an_item_to_their_own_cell()
    {
        $user = User::factory()->create();
        $cell = $this->createCellFor($user);

        $response = $this->actingAs($user)->post(route('matrix-cell-items.store', $cell), [
            'title' => '受注バグ修正',
            'memo' => '再現手順を先に確認する',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('matrix_cell_items', [
            'matrix_cell_id' => $cell->id,
            'title' => '受注バグ修正',
            'memo' => '再現手順を先に確認する',
            'is_completed' => false,
            'sort_order' => 1,
        ]);
    }

    public function test_new_items_are_appended_to_the_end_of_the_cell()
    {
        $user = User::factory()->create();
        $cell = $this->createCellFor($user);
        MatrixCellItem::factory()->create(['matrix_cell_id' => $cell->id, 'sort_order' => 3]);

        $this->actingAs($user)->post(route('matrix-cell-items.store', $cell), [
            'title' => '末尾の項目',
        ])->assertRedirect();

        $this->assertDatabaseHas('matrix_cell_items', [
            'matrix_cell_id' => $cell->id,
            'title' => '末尾の項目',
            'sort_order' => 4,
        ]);
    }

    public function test_user_cannot_add_an_item_to_another_users_cell()
    {
        $user = User::factory()->create();
        $otherCell = $this->createCellFor(User::factory()->create());

        $this->actingAs($user)->post(route('matrix-cell-items.store', $otherCell), [
            'title' => '不正な項目',
        ])->assertForbidden();

        $this->assertDatabaseMissing('matrix_cell_items', ['title' => '不正な項目']);
    }

    public function test_item_title_is_validated()
    {
        $user = User::factory()->create();
        $cell = $this->createCellFor($user);

        $this->actingAs($user)
            ->post(route('matrix-cell-items.store', $cell), ['title' => ''])
            ->assertSessionHasErrors('title');
    }

    public function test_user_can_update_their_own_item()
    {
        $user = User::factory()->create();
        $cell = $this->createCellFor($user);
        $item = MatrixCellItem::factory()->create(['matrix_cell_id' => $cell->id]);

        $response = $this->actingAs($user)->patch(route('matrix-cell-items.update', $item), [
            'title' => '更新後の題名',
            'memo' => '更新後のメモ',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('matrix_cell_items', [
            'id' => $item->id,
            'title' => '更新後の題名',
            'memo' => '更新後のメモ',
        ]);
    }

    public function test_user_cannot_update_another_users_item()
    {
        $user = User::factory()->create();
        $otherCell = $this->createCellFor(User::factory()->create());
        $otherItem = MatrixCellItem::factory()->create([
            'matrix_cell_id' => $otherCell->id,
            'title' => '他人の項目',
        ]);

        $this->actingAs($user)->patch(route('matrix-cell-items.update', $otherItem), [
            'title' => '乗っ取り',
        ])->assertForbidden();

        $this->assertDatabaseHas('matrix_cell_items', [
            'id' => $otherItem->id,
            'title' => '他人の項目',
        ]);
    }

    public function test_user_can_soft_delete_their_own_item()
    {
        $user = User::factory()->create();
        $cell = $this->createCellFor($user);
        $item = MatrixCellItem::factory()->create(['matrix_cell_id' => $cell->id]);

        $this->actingAs($user)->delete(route('matrix-cell-items.destroy', $item))->assertRedirect();

        $this->assertSoftDeleted('matrix_cell_items', ['id' => $item->id]);
    }

    public function test_user_cannot_delete_another_users_item()
    {
        $user = User::factory()->create();
        $otherCell = $this->createCellFor(User::factory()->create());
        $otherItem = MatrixCellItem::factory()->create(['matrix_cell_id' => $otherCell->id]);

        $this->actingAs($user)->delete(route('matrix-cell-items.destroy', $otherItem))->assertForbidden();

        $this->assertNull($otherItem->refresh()->deleted_at);
    }

    public function test_completing_an_item_on_the_current_row_records_an_activity_log()
    {
        $user = User::factory()->create();
        $cell = $this->createCellFor($user, MatrixRowKey::Current);
        $item = MatrixCellItem::factory()->create(['matrix_cell_id' => $cell->id]);

        $this->actingAs($user)->patch(route('matrix-cell-items.toggle', $item))->assertRedirect();

        $item->refresh();
        $this->assertTrue($item->is_completed);
        $this->assertNotNull($item->completed_at);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'event_type' => 'matrix_item_completed',
            'subject_type' => 'matrix_cell_item',
            'subject_id' => $item->id,
        ]);
    }

    public function test_reopening_an_item_clears_completed_at_and_keeps_the_completed_log()
    {
        $user = User::factory()->create();
        $cell = $this->createCellFor($user, MatrixRowKey::Current);
        $item = MatrixCellItem::factory()->completed()->create(['matrix_cell_id' => $cell->id]);

        $this->actingAs($user)->patch(route('matrix-cell-items.toggle', $item))->assertRedirect();

        $item->refresh();
        $this->assertFalse($item->is_completed);
        $this->assertNull($item->completed_at);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'event_type' => 'matrix_item_reopened',
            'subject_type' => 'matrix_cell_item',
            'subject_id' => $item->id,
        ]);
    }

    public function test_toggling_twice_appends_events_without_deleting_previous_logs()
    {
        $user = User::factory()->create();
        $cell = $this->createCellFor($user, MatrixRowKey::Current);
        $item = MatrixCellItem::factory()->create(['matrix_cell_id' => $cell->id]);

        $this->actingAs($user)->patch(route('matrix-cell-items.toggle', $item))->assertRedirect();
        $this->actingAs($user)->patch(route('matrix-cell-items.toggle', $item))->assertRedirect();

        // 不変ログ: 完了 + 再開の 2 イベントが両方残る
        $events = $user->activityLogs()->orderBy('occurred_at')->pluck('event_type');
        $this->assertCount(2, $events);
        $this->assertSame('matrix_item_completed', $events->first()->value);
        $this->assertSame('matrix_item_reopened', $events->last()->value);
    }

    public function test_items_on_monthly_and_future_rows_cannot_be_toggled()
    {
        $user = User::factory()->create();

        foreach ([MatrixRowKey::Monthly, MatrixRowKey::Future] as $rowKey) {
            $cell = $this->createCellFor($user, $rowKey);
            $item = MatrixCellItem::factory()->create(['matrix_cell_id' => $cell->id]);

            $this->actingAs($user)
                ->patch(route('matrix-cell-items.toggle', $item))
                ->assertForbidden();

            $this->assertFalse($item->refresh()->is_completed);
        }

        $this->assertSame(0, $user->activityLogs()->count());
    }

    public function test_user_cannot_toggle_another_users_item()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherCell = $this->createCellFor($otherUser, MatrixRowKey::Current);
        $otherItem = MatrixCellItem::factory()->create(['matrix_cell_id' => $otherCell->id]);

        $this->actingAs($user)
            ->patch(route('matrix-cell-items.toggle', $otherItem))
            ->assertForbidden();

        $this->assertFalse($otherItem->refresh()->is_completed);
        $this->assertSame(0, $otherUser->activityLogs()->count());
    }

    public function test_matrix_cells_are_unique_per_user_area_and_row()
    {
        $user = User::factory()->create();
        $cell = $this->createCellFor($user, MatrixRowKey::Current);

        $this->expectException(UniqueConstraintViolationException::class);

        MatrixCell::factory()->forRow(MatrixRowKey::Current)->create([
            'user_id' => $user->id,
            'life_area_id' => $cell->life_area_id,
        ]);
    }
}
