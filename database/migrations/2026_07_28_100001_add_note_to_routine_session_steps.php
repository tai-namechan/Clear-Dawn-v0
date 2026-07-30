<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * プランのステップが持つキュー（今日の1点・今週の処方）を実行セッションへ引き継ぐ。
 *
 * これまで item_name / purpose / 目標値だけをスナップショットしていたため、
 * 実行画面ではタイトルしか出ていなかった（ADR-0006 のスナップショット原則に従い、
 * 実行時点の文言を固定して保持する）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routine_session_steps', function (Blueprint $table) {
            $table->string('note')->nullable()->after('purpose');
        });
    }

    public function down(): void
    {
        Schema::table('routine_session_steps', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
