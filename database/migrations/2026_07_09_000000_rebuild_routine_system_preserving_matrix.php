<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-0007 は create migration を直接書き換えたため、本番では旧 Training スキーマのまま
 * migrations だけ「済」になっている。users / matrix / videos / metrics は保持し、
 * ルーティン系だけ DROP → CREATE する。
 *
 * 検出:
 * - exercises / training_* がある
 * - または routine_steps に exercise_id がある / routine_item_id が無い
 *
 * 既に新スキーマなら no-op（冪等）。
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->isAlreadyConverted()) {
            return;
        }

        $this->dropLegacyAndPartialRoutineTables();
        $this->createRoutineSystemTables();
    }

    public function down(): void
    {
        // 本番データ保持のための一方向 migration。旧 Training スキーマへは戻さない。
    }

    private function isAlreadyConverted(): bool
    {
        if (Schema::hasTable('exercises') || Schema::hasTable('training_plans') || Schema::hasTable('training_runs')) {
            return false;
        }

        if (! Schema::hasTable('routine_items') || ! Schema::hasTable('routine_steps')) {
            return false;
        }

        if (Schema::hasColumn('routine_steps', 'exercise_id')) {
            return false;
        }

        return Schema::hasColumn('routine_steps', 'routine_item_id')
            && Schema::hasTable('routine_plans')
            && Schema::hasTable('routine_sessions')
            && Schema::hasTable('routine_block_logs');
    }

    private function dropLegacyAndPartialRoutineTables(): void
    {
        Schema::dropIfExists('training_set_logs');
        Schema::dropIfExists('training_run_steps');
        Schema::dropIfExists('training_runs');
        Schema::dropIfExists('training_plan_steps');
        Schema::dropIfExists('training_plans');

        Schema::dropIfExists('routine_block_logs');
        Schema::dropIfExists('routine_session_steps');
        Schema::dropIfExists('routine_sessions');
        Schema::dropIfExists('routine_plan_steps');
        Schema::dropIfExists('routine_plans');
        Schema::dropIfExists('routine_steps');
        Schema::dropIfExists('routines');

        $this->dropVideosLegacyColumns();

        Schema::dropIfExists('exercises');
        Schema::dropIfExists('routine_items');
    }

    private function dropVideosLegacyColumns(): void
    {
        if (! Schema::hasTable('videos')) {
            return;
        }

        foreach (['exercise_id', 'routine_item_id'] as $column) {
            if (! Schema::hasColumn('videos', $column)) {
                continue;
            }

            Schema::table('videos', function (Blueprint $table) use ($column) {
                if (Schema::hasIndex('videos', ['user_id', $column])) {
                    $table->dropIndex(['user_id', $column]);
                }

                $table->dropConstrainedForeignId($column);
            });
        }
    }

    private function createRoutineSystemTables(): void
    {
        Schema::create('routine_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('life_area_id')->nullable()->constrained('life_areas')->nullOnDelete();
            $table->string('name');
            $table->string('category');
            $table->string('tracking_type');
            $table->string('default_load_unit')->nullable();
            $table->string('default_amount_unit')->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        if (Schema::hasTable('videos') && ! Schema::hasColumn('videos', 'routine_item_id')) {
            Schema::table('videos', function (Blueprint $table) {
                $table->foreignUlid('routine_item_id')
                    ->nullable()
                    ->constrained('routine_items')
                    ->nullOnDelete();
                $table->index(['user_id', 'routine_item_id']);
            });
        }

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

            $table->index(['user_id', 'is_active', 'sort_order']);
        });

        Schema::create('routine_steps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('routine_id')->constrained('routines')->cascadeOnDelete();
            $table->foreignUlid('routine_item_id')->constrained('routine_items')->restrictOnDelete();
            $table->foreignUlid('video_id')->nullable()->constrained('videos')->nullOnDelete();
            $table->string('purpose')->nullable();
            $table->unsignedInteger('sort_order');
            $table->decimal('target_load', 8, 2)->nullable();
            $table->string('load_unit')->nullable();
            $table->decimal('target_amount', 8, 2)->nullable();
            $table->string('amount_unit')->nullable();
            $table->unsignedInteger('target_blocks')->nullable();
            $table->unsignedInteger('rest_seconds')->nullable();
            $table->string('note')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['routine_id', 'sort_order']);
        });

        Schema::create('routine_plans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('life_area_id')->nullable()->constrained('life_areas')->nullOnDelete();
            $table->foreignUlid('routine_id')->nullable()->constrained('routines')->nullOnDelete();
            $table->string('title');
            $table->date('scheduled_on');
            $table->string('status');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'scheduled_on']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('routine_plan_steps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('routine_plan_id')->constrained('routine_plans')->cascadeOnDelete();
            $table->foreignUlid('routine_item_id')->constrained('routine_items')->restrictOnDelete();
            $table->foreignUlid('video_id')->nullable()->constrained('videos')->nullOnDelete();
            $table->string('purpose')->nullable();
            $table->unsignedInteger('sort_order');
            $table->decimal('target_load', 8, 2)->nullable();
            $table->string('load_unit')->nullable();
            $table->decimal('target_amount', 8, 2)->nullable();
            $table->string('amount_unit')->nullable();
            $table->unsignedInteger('target_blocks')->nullable();
            $table->unsignedInteger('rest_seconds')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['routine_plan_id', 'sort_order']);
        });

        Schema::create('routine_sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('routine_plan_id')->constrained('routine_plans')->restrictOnDelete();
            $table->string('status');
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'started_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('routine_session_steps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('routine_session_id')->constrained('routine_sessions')->cascadeOnDelete();
            $table->foreignUlid('routine_item_id')->nullable()->constrained('routine_items')->nullOnDelete();
            $table->string('item_name');
            $table->foreignUlid('video_id')->nullable()->constrained('videos')->nullOnDelete();
            $table->string('purpose')->nullable();
            $table->unsignedInteger('sort_order');
            $table->decimal('target_load', 8, 2)->nullable();
            $table->string('load_unit')->nullable();
            $table->decimal('target_amount', 8, 2)->nullable();
            $table->string('amount_unit')->nullable();
            $table->unsignedInteger('target_blocks')->nullable();
            $table->unsignedInteger('rest_seconds')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('actual_duration_seconds')->nullable();
            $table->text('memo')->nullable();
            $table->timestamps();

            $table->index(['routine_session_id', 'sort_order']);
        });

        Schema::create('routine_block_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('routine_session_step_id')->constrained('routine_session_steps')->cascadeOnDelete();
            $table->unsignedInteger('block_number');
            $table->decimal('load_value', 8, 2)->nullable();
            $table->string('load_unit')->nullable();
            $table->decimal('amount_value', 8, 2)->nullable();
            $table->string('amount_unit')->nullable();
            $table->string('memo')->nullable();
            $table->timestamps();

            $table->unique(['routine_session_step_id', 'block_number']);
        });
    }
};
