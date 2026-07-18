<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * metrics をユーザー定義指標に対応させる（グローバルマスタ行は user_id = null のまま）。
 * is_advanced = 専門測定値（既定で非表示。例: thorax angular velocity）。
 *
 * key のユニークはグローバル一意から (user_id, key) に変更する。ユーザー定義指標が
 * グローバル指標と同じ key を持てるようにするため（user_id = null 行の一意性は
 * seeder / アプリ層で担保する）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metrics', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->text('description_plain')->nullable()->after('sort_order');
            $table->string('measurement_method')->nullable()->after('description_plain');
            $table->boolean('is_advanced')->default(false)->after('measurement_method');
            $table->dropUnique(['key']);
            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::table('metrics', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'key']);
            $table->unique(['key']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['description_plain', 'measurement_method', 'is_advanced']);
        });
    }
};
