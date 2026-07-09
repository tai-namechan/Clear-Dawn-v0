<?php

namespace Tests\Feature;

use App\Models\LifeArea;
use App\Models\MatrixCell;
use App\Models\MatrixCellItem;
use App\Models\User;
use App\Models\Video;
use Database\Seeders\MatrixRowSeeder;
use Database\Seeders\MetricSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RebuildRoutineSystemMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MatrixRowSeeder::class);
        $this->seed(MetricSeeder::class);
    }

    public function test_rebuild_migration_is_noop_when_schema_is_already_converted(): void
    {
        $this->assertTrue(Schema::hasColumn('routine_steps', 'routine_item_id'));

        $this->runRebuildMigration();

        $this->assertTrue(Schema::hasTable('routine_items'));
        $this->assertTrue(Schema::hasColumn('routine_steps', 'routine_item_id'));
        $this->assertFalse(Schema::hasTable('exercises'));
    }

    public function test_rebuild_migration_converts_legacy_training_schema_and_preserves_matrix(): void
    {
        $user = User::factory()->create(['email' => 'keep-me@example.com']);
        $lifeArea = LifeArea::factory()->create([
            'user_id' => $user->id,
            'name' => '保持される領域',
        ]);
        $cell = MatrixCell::factory()->create([
            'user_id' => $user->id,
            'life_area_id' => $lifeArea->id,
        ]);
        $item = MatrixCellItem::factory()->create([
            'matrix_cell_id' => $cell->id,
            'title' => '保持されるマトリクス項目',
        ]);
        $video = Video::factory()->ready()->create([
            'user_id' => $user->id,
            'title' => '保持される動画',
        ]);

        $this->installLegacyTrainingSchema($user->id, $lifeArea->id, $video->id);

        $this->assertTrue(Schema::hasTable('exercises'));
        $this->assertTrue(Schema::hasColumn('routine_steps', 'exercise_id'));
        $this->assertFalse(Schema::hasColumn('routine_steps', 'routine_item_id'));

        $this->runRebuildMigration();
        $this->runTitleAndDefaultVideoMigration();

        $this->assertFalse(Schema::hasTable('exercises'));
        $this->assertFalse(Schema::hasTable('training_plans'));
        $this->assertTrue(Schema::hasTable('routine_items'));
        $this->assertTrue(Schema::hasColumn('routine_steps', 'routine_item_id'));
        $this->assertTrue(Schema::hasColumn('routine_steps', 'title'));
        $this->assertTrue(Schema::hasColumn('routine_items', 'default_video_id'));
        $this->assertTrue(Schema::hasColumn('videos', 'routine_item_id'));
        $this->assertFalse(Schema::hasColumn('videos', 'exercise_id'));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'keep-me@example.com']);
        $this->assertDatabaseHas('life_areas', ['id' => $lifeArea->id, 'name' => '保持される領域']);
        $this->assertDatabaseHas('matrix_cell_items', ['id' => $item->id, 'title' => '保持されるマトリクス項目']);
        $this->assertDatabaseHas('videos', ['id' => $video->id, 'title' => '保持される動画']);
    }

    private function runRebuildMigration(): void
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_07_09_000000_rebuild_routine_system_preserving_matrix.php');
        $migration->up();
    }

    private function runTitleAndDefaultVideoMigration(): void
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_07_09_000001_add_title_and_default_video_to_routine_tables.php');
        $migration->up();
    }

    private function installLegacyTrainingSchema(int $userId, string $lifeAreaId, string $videoId): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('routine_block_logs');
        Schema::dropIfExists('routine_session_steps');
        Schema::dropIfExists('routine_sessions');
        Schema::dropIfExists('routine_plan_steps');
        Schema::dropIfExists('routine_plans');
        Schema::dropIfExists('routine_steps');
        Schema::dropIfExists('routines');
        Schema::dropIfExists('routine_items');

        if (Schema::hasColumn('videos', 'routine_item_id')) {
            Schema::table('videos', function (Blueprint $table) {
                if (Schema::hasIndex('videos', ['user_id', 'routine_item_id'])) {
                    $table->dropIndex(['user_id', 'routine_item_id']);
                }
                $table->dropConstrainedForeignId('routine_item_id');
            });
        }

        Schema::enableForeignKeyConstraints();

        Schema::create('exercises', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('life_area_id')->nullable()->constrained('life_areas')->nullOnDelete();
            $table->string('name');
            $table->string('category');
            $table->string('tracking_type');
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->foreignUlid('exercise_id')->nullable()->constrained('exercises')->nullOnDelete();
            $table->index(['user_id', 'exercise_id']);
        });

        Schema::create('routines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('life_area_id')->nullable()->constrained('life_areas')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('routine_steps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('routine_id')->constrained('routines')->cascadeOnDelete();
            $table->foreignUlid('exercise_id')->constrained('exercises')->restrictOnDelete();
            $table->foreignUlid('video_id')->nullable()->constrained('videos')->nullOnDelete();
            $table->string('purpose')->nullable();
            $table->unsignedInteger('sort_order');
            $table->unsignedInteger('target_sets')->nullable();
            $table->unsignedInteger('target_reps')->nullable();
            $table->decimal('target_weight_kg', 6, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('training_plans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('scheduled_on');
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('training_plan_steps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('training_plan_id')->constrained('training_plans')->cascadeOnDelete();
            $table->foreignUlid('exercise_id')->constrained('exercises')->restrictOnDelete();
            $table->unsignedInteger('sort_order');
            $table->timestamps();
        });

        Schema::create('training_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('training_plan_id')->constrained('training_plans')->restrictOnDelete();
            $table->string('status');
            $table->dateTime('started_at');
            $table->timestamps();
        });

        Schema::create('training_run_steps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('training_run_id')->constrained('training_runs')->cascadeOnDelete();
            $table->string('exercise_name');
            $table->unsignedInteger('sort_order');
            $table->timestamps();
        });

        Schema::create('training_set_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('training_run_step_id')->constrained('training_run_steps')->cascadeOnDelete();
            $table->unsignedInteger('set_number');
            $table->timestamps();
        });

        $exerciseId = (string) str()->ulid();
        DB::table('exercises')->insert([
            'id' => $exerciseId,
            'user_id' => $userId,
            'life_area_id' => $lifeAreaId,
            'name' => '旧スクワット',
            'category' => 'strength',
            'tracking_type' => 'weight_reps',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('videos')->where('id', $videoId)->update(['exercise_id' => $exerciseId]);

        $routineId = (string) str()->ulid();
        DB::table('routines')->insert([
            'id' => $routineId,
            'user_id' => $userId,
            'life_area_id' => $lifeAreaId,
            'name' => '旧ルーティン',
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('routine_steps')->insert([
            'id' => (string) str()->ulid(),
            'routine_id' => $routineId,
            'exercise_id' => $exerciseId,
            'video_id' => $videoId,
            'sort_order' => 1,
            'target_sets' => 3,
            'target_reps' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
