<?php

namespace Tests\Feature;

use App\Enums\MatrixRowKey;
use App\Models\MatrixRow;
use Database\Seeders\MatrixRowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatrixRowSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_the_three_fixed_rows()
    {
        $this->seed(MatrixRowSeeder::class);

        $this->assertSame(3, MatrixRow::count());

        $this->assertDatabaseHas('matrix_rows', [
            'key' => 'monthly',
            'label' => '1ヶ月でやるべきこと',
            'sort_order' => 1,
            'is_checkable' => false,
        ]);
        $this->assertDatabaseHas('matrix_rows', [
            'key' => 'current',
            'label' => '今やるべきこと',
            'sort_order' => 2,
            'is_checkable' => true,
        ]);
        $this->assertDatabaseHas('matrix_rows', [
            'key' => 'future',
            'label' => '将来どうなっていたいか',
            'sort_order' => 3,
            'is_checkable' => false,
        ]);
    }

    public function test_seeder_is_idempotent()
    {
        $this->seed(MatrixRowSeeder::class);

        $idsAfterFirstRun = MatrixRow::query()->orderBy('key')->pluck('id')->all();

        $this->seed(MatrixRowSeeder::class);

        $this->assertSame(3, MatrixRow::count());
        // 再実行しても既存行が置き換わらない（ID が変わらない）
        $this->assertSame($idsAfterFirstRun, MatrixRow::query()->orderBy('key')->pluck('id')->all());
    }

    public function test_only_the_current_row_is_checkable()
    {
        $this->seed(MatrixRowSeeder::class);

        $checkableKeys = MatrixRow::query()
            ->where('is_checkable', true)
            ->pluck('key');

        $this->assertCount(1, $checkableKeys);
        $this->assertSame(MatrixRowKey::Current, $checkableKeys->first());
    }
}
