<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * after('routine_item_id') は本番の旧スキーマで落ちるため使わない。
 * MySQL は失敗前に部分適用し得るため hasColumn で冪等にする。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('routine_items') && ! Schema::hasColumn('routine_items', 'default_video_id')) {
            Schema::table('routine_items', function (Blueprint $table) {
                $table->foreignUlid('default_video_id')
                    ->nullable()
                    ->constrained('videos')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('routine_steps') && ! Schema::hasColumn('routine_steps', 'title')) {
            Schema::table('routine_steps', function (Blueprint $table) {
                $table->string('title')->nullable();
            });
        }

        if (Schema::hasTable('routine_plan_steps') && ! Schema::hasColumn('routine_plan_steps', 'title')) {
            Schema::table('routine_plan_steps', function (Blueprint $table) {
                $table->string('title')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('routine_plan_steps') && Schema::hasColumn('routine_plan_steps', 'title')) {
            Schema::table('routine_plan_steps', function (Blueprint $table) {
                $table->dropColumn('title');
            });
        }

        if (Schema::hasTable('routine_steps') && Schema::hasColumn('routine_steps', 'title')) {
            Schema::table('routine_steps', function (Blueprint $table) {
                $table->dropColumn('title');
            });
        }

        if (Schema::hasTable('routine_items') && Schema::hasColumn('routine_items', 'default_video_id')) {
            Schema::table('routine_items', function (Blueprint $table) {
                $table->dropConstrainedForeignId('default_video_id');
            });
        }
    }
};
