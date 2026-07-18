<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * プログラム由来プランは「同一版・同一日に 1 件」を DB で保証する
 * （sequential モードの二重生成防止。program_version_id が null の手動プランには影響しない）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routine_plans', function (Blueprint $table) {
            $table->unique(
                ['user_id', 'program_version_id', 'scheduled_on'],
                'routine_plans_program_day_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('routine_plans', function (Blueprint $table) {
            $table->dropUnique('routine_plans_program_day_unique');
        });
    }
};
