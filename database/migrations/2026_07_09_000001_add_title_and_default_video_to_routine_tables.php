<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 本番スキーマは create マイグレーション書き換え（ADR-0007）前の状態が残っている場合がある。
 * after('routine_item_id') は列が無いと MySQL で落ちるため使わない。
 * また MySQL の DDL は失敗時に部分適用され得るため、hasColumn で冪等にする。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routine_items', function (Blueprint $table) {
            if (! Schema::hasColumn('routine_items', 'default_video_id')) {
                $table->foreignUlid('default_video_id')
                    ->nullable()
                    ->constrained('videos')
                    ->nullOnDelete();
            }
        });

        Schema::table('routine_steps', function (Blueprint $table) {
            if (! Schema::hasColumn('routine_steps', 'title')) {
                $table->string('title')->nullable();
            }
        });

        Schema::table('routine_plan_steps', function (Blueprint $table) {
            if (! Schema::hasColumn('routine_plan_steps', 'title')) {
                $table->string('title')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('routine_plan_steps', function (Blueprint $table) {
            if (Schema::hasColumn('routine_plan_steps', 'title')) {
                $table->dropColumn('title');
            }
        });

        Schema::table('routine_steps', function (Blueprint $table) {
            if (Schema::hasColumn('routine_steps', 'title')) {
                $table->dropColumn('title');
            }
        });

        Schema::table('routine_items', function (Blueprint $table) {
            if (Schema::hasColumn('routine_items', 'default_video_id')) {
                $table->dropConstrainedForeignId('default_video_id');
            }
        });
    }
};
