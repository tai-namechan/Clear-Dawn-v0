<?php

namespace Tests\Feature\Yoyu;

use App\Domain\Yoyu\Models\YoyuPlace;
use App\Domain\Yoyu\Services\YoyuPlaceTravelService;
use App\Domain\Yoyu\Support\PlaceNameNormalizer;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class YoyuPlaceNormalizedNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_name_reregistration_updates_minutes(): void
    {
        $user = User::factory()->create();
        $service = app(YoyuPlaceTravelService::class);

        $service->upsert($user->id, 'ジム', 25);
        $service->upsert($user->id, 'ジム', 40);

        $this->assertSame(1, YoyuPlace::query()->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('yoyu_places', [
            'user_id' => $user->id,
            'name' => 'ジム',
            'normalized_name' => 'ジム',
            'travel_minutes' => 40,
        ]);
    }

    public function test_normalized_variants_collapse_to_one_row(): void
    {
        $user = User::factory()->create();
        $service = app(YoyuPlaceTravelService::class);

        $service->upsert($user->id, 'Tokyo Gym', 10);
        $service->upsert($user->id, '  tokyo   gym ', 15);
        $service->upsert($user->id, 'Ｔｏｋｙｏ　Ｇｙｍ', 20);

        $this->assertSame(1, YoyuPlace::query()->where('user_id', $user->id)->count());
        $this->assertSame('tokyogym', YoyuPlace::query()->where('user_id', $user->id)->value('normalized_name'));
        $this->assertSame(20, (int) YoyuPlace::query()->where('user_id', $user->id)->value('travel_minutes'));
        $this->assertSame('Tokyo Gym', YoyuPlace::query()->where('user_id', $user->id)->value('name'));
    }

    public function test_other_users_keep_separate_rows(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $service = app(YoyuPlaceTravelService::class);

        $service->upsert($user->id, 'ジム', 10);
        $service->upsert($other->id, 'ジム', 50);

        $this->assertSame(1, YoyuPlace::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, YoyuPlace::query()->where('user_id', $other->id)->count());
        $this->assertDatabaseHas('yoyu_places', ['user_id' => $user->id, 'travel_minutes' => 10]);
        $this->assertDatabaseHas('yoyu_places', ['user_id' => $other->id, 'travel_minutes' => 50]);
    }

    public function test_concurrent_double_upsert_keeps_one_row(): void
    {
        $user = User::factory()->create();
        $service = app(YoyuPlaceTravelService::class);

        $service->upsert($user->id, '駅', 5);
        $service->upsert($user->id, ' 駅 ', 7);
        $service->upsert($user->id, '駅', 9);

        $key = PlaceNameNormalizer::normalize('駅');
        $this->assertSame(1, YoyuPlace::query()->where('user_id', $user->id)->where('normalized_name', $key)->count());
        $this->assertSame(9, (int) YoyuPlace::query()->where('user_id', $user->id)->value('travel_minutes'));
    }

    public function test_unique_index_is_on_normalized_name_not_raw_name(): void
    {
        $indexes = collect(Schema::getIndexes('yoyu_places'));
        $names = $indexes->pluck('name')->all();

        $this->assertContains('yoyu_places_user_id_normalized_name_unique', $names);
        $this->assertNotContains('yoyu_places_user_id_name_unique', $names);

        $normalized = $indexes->firstWhere('name', 'yoyu_places_user_id_normalized_name_unique');
        $this->assertNotNull($normalized);
        $this->assertSame(['user_id', 'normalized_name'], $normalized['columns']);
    }

    public function test_upgrade_backfill_dedupes_normalized_duplicates(): void
    {
        $user = User::factory()->create();

        Schema::table('yoyu_places', function (Blueprint $table) {
            $table->dropUnique('yoyu_places_user_id_normalized_name_unique');
        });

        // Seed pre-normalization-era duplicates that share a normalized key.
        DB::table('yoyu_places')->insert([
            [
                'id' => (string) str()->ulid(),
                'user_id' => $user->id,
                'name' => 'Tokyo Gym',
                'normalized_name' => PlaceNameNormalizer::normalize('Tokyo Gym'),
                'travel_minutes' => 10,
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ],
            [
                'id' => (string) str()->ulid(),
                'user_id' => $user->id,
                'name' => 'tokyo gym',
                'normalized_name' => PlaceNameNormalizer::normalize('tokyo gym'),
                'travel_minutes' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $migration = require database_path('migrations/2026_07_12_064306_repair_yoyu_places_normalized_name_unique.php');
        $migration->up();

        $this->assertSame(1, YoyuPlace::query()->where('user_id', $user->id)->count());
        $this->assertSame(30, (int) YoyuPlace::query()->where('user_id', $user->id)->value('travel_minutes'));
        $this->assertSame('tokyo gym', YoyuPlace::query()->where('user_id', $user->id)->value('name'));
        $this->assertContains(
            'yoyu_places_user_id_normalized_name_unique',
            collect(Schema::getIndexes('yoyu_places'))->pluck('name')->all(),
        );
    }

    public function test_normalized_name_migration_can_roll_back_column_from_220001(): void
    {
        $this->assertTrue(Schema::hasColumn('yoyu_places', 'normalized_name'));

        $migration = require database_path('migrations/2026_07_11_220001_add_unique_user_name_to_yoyu_places_table.php');
        $migration->down();

        $this->assertFalse(Schema::hasColumn('yoyu_places', 'normalized_name'));

        $migration->up();

        $this->assertTrue(Schema::hasColumn('yoyu_places', 'normalized_name'));
        $indexes = collect(Schema::getIndexes('yoyu_places'))->pluck('name');
        $this->assertTrue($indexes->contains('yoyu_places_user_id_normalized_name_unique'));
    }
}
