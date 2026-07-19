<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2: Program → RoutinePlan 連携（ADR-0012）。
 * 新テーブルは作らず、既存 routine_* に nullable 列のみ追加する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routine_plans', function (Blueprint $table) {
            $table->foreignUlid('program_version_id')->nullable()->after('routine_id')->constrained('program_versions')->nullOnDelete();
            $table->foreignUlid('program_week_id')->nullable()->after('program_version_id')->constrained('program_weeks')->nullOnDelete();
            $table->foreignUlid('program_day_template_id')->nullable()->after('program_week_id')->constrained('program_day_templates')->nullOnDelete();
            $table->string('generation_source')->nullable()->after('program_day_template_id');
            $table->foreignUlid('choice_option_id')->nullable()->after('generation_source')->constrained('program_choice_options')->nullOnDelete();
            $table->text('choice_reason')->nullable()->after('choice_option_id');
            $table->text('repeat_reason')->nullable()->after('choice_reason');
            $table->text('adjustment_reason')->nullable()->after('repeat_reason');

            $table->index(['user_id', 'scheduled_on', 'program_day_template_id']);
        });

        Schema::table('routine_plan_steps', function (Blueprint $table) {
            $table->foreignUlid('program_step_item_id')->nullable()->after('routine_item_id')->constrained('program_step_items')->nullOnDelete();
            $table->string('step_kind')->nullable()->after('purpose');
            $table->string('required_level')->nullable()->after('step_kind');
        });

        Schema::table('routine_sessions', function (Blueprint $table) {
            $table->decimal('session_rpe', 3, 1)->nullable()->after('note');
        });

        Schema::table('routine_session_steps', function (Blueprint $table) {
            $table->string('status_reason')->nullable()->after('memo');
            $table->unsignedTinyInteger('pain_score')->nullable()->after('status_reason');
            $table->string('pain_location')->nullable()->after('pain_score');
        });

        Schema::table('routine_block_logs', function (Blueprint $table) {
            $table->decimal('rpe', 3, 1)->nullable()->after('memo');
            $table->decimal('distance_value', 8, 2)->nullable()->after('rpe');
            $table->unsignedInteger('duration_seconds')->nullable()->after('distance_value');
            $table->string('side')->nullable()->after('duration_seconds');
            $table->json('extra')->nullable()->after('side');
        });

        Schema::table('routine_items', function (Blueprint $table) {
            $table->json('resource_weights')->nullable()->after('note');
            $table->unsignedTinyInteger('neural_demand')->nullable()->after('resource_weights');
            $table->string('throw_type')->nullable()->after('neural_demand');
            $table->json('flags')->nullable()->after('throw_type');
            $table->text('plain_description')->nullable()->after('flags');
        });
    }

    public function down(): void
    {
        Schema::table('routine_items', function (Blueprint $table) {
            $table->dropColumn(['resource_weights', 'neural_demand', 'throw_type', 'flags', 'plain_description']);
        });

        Schema::table('routine_block_logs', function (Blueprint $table) {
            $table->dropColumn(['rpe', 'distance_value', 'duration_seconds', 'side', 'extra']);
        });

        Schema::table('routine_session_steps', function (Blueprint $table) {
            $table->dropColumn(['status_reason', 'pain_score', 'pain_location']);
        });

        Schema::table('routine_sessions', function (Blueprint $table) {
            $table->dropColumn('session_rpe');
        });

        Schema::table('routine_plan_steps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_step_item_id');
            $table->dropColumn(['step_kind', 'required_level']);
        });

        Schema::table('routine_plans', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'scheduled_on', 'program_day_template_id']);
            $table->dropConstrainedForeignId('program_version_id');
            $table->dropConstrainedForeignId('program_week_id');
            $table->dropConstrainedForeignId('program_day_template_id');
            $table->dropConstrainedForeignId('choice_option_id');
            $table->dropColumn(['generation_source', 'choice_reason', 'repeat_reason', 'adjustment_reason']);
        });
    }
};
