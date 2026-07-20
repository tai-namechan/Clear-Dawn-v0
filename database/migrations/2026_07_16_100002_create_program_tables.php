<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * プログラム層（ADR-0012）。Program → Version → Phase/Week → DAY → STEP → 種目処方。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('goal_id')->nullable()->constrained('goals')->nullOnDelete();
            $table->string('name');
            $table->text('purpose')->nullable();
            $table->text('design_philosophy')->nullable();
            $table->string('status');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('program_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('program_id')->constrained('programs')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->text('change_summary')->nullable();
            $table->text('change_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['program_id', 'version_number']);
        });

        Schema::create('program_phases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('program_version_id')->constrained('program_versions')->cascadeOnDelete();
            $table->string('name');
            $table->string('intent');
            $table->unsignedInteger('sort_order');
            $table->text('progression_conditions')->nullable();
            $table->timestamps();
        });

        Schema::create('program_weeks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('program_version_id')->constrained('program_versions')->cascadeOnDelete();
            $table->foreignUlid('program_phase_id')->constrained('program_phases')->cascadeOnDelete();
            $table->unsignedInteger('week_number');
            $table->date('starts_on');
            $table->string('intent')->nullable();
            $table->timestamps();

            $table->unique(['program_version_id', 'week_number']);
        });

        Schema::create('program_day_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('program_version_id')->constrained('program_versions')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('priority_tier');
            $table->string('assignment_mode');
            $table->unsignedTinyInteger('fixed_weekday')->nullable()->comment('ISO-8601: 1=月 .. 7=日');
            $table->unsignedInteger('estimated_minutes_min')->nullable();
            $table->unsignedInteger('estimated_minutes_max')->nullable();
            $table->boolean('is_optional')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['program_version_id', 'code']);
        });

        Schema::create('program_choice_groups', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('program_day_template_id')->constrained('program_day_templates')->cascadeOnDelete();
            $table->string('name');
            $table->text('selection_hint')->nullable();
            $table->timestamps();
        });

        Schema::create('program_choice_options', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('program_choice_group_id')->constrained('program_choice_groups')->cascadeOnDelete();
            $table->string('label');
            $table->text('description')->nullable();
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->unsignedInteger('sort_order');
            $table->timestamps();
        });

        Schema::create('program_day_steps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('program_day_template_id')->constrained('program_day_templates')->cascadeOnDelete();
            $table->foreignUlid('program_choice_option_id')->nullable()->constrained('program_choice_options')->nullOnDelete();
            $table->string('name');
            $table->string('step_kind');
            $table->unsignedInteger('sort_order');
            $table->string('required_level');
            $table->string('purpose')->nullable();
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->text('start_condition')->nullable();
            $table->text('completion_condition')->nullable();
            $table->text('abort_condition')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['program_day_template_id', 'sort_order']);
        });

        Schema::create('program_step_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('program_day_step_id')->constrained('program_day_steps')->cascadeOnDelete();
            $table->foreignUlid('routine_item_id')->constrained('routine_items')->restrictOnDelete();
            $table->unsignedInteger('sort_order');
            $table->unsignedInteger('sets')->nullable();
            $table->unsignedInteger('reps')->nullable();
            $table->decimal('amount_value', 8, 2)->nullable();
            $table->string('amount_unit')->nullable();
            $table->decimal('fixed_load', 8, 2)->nullable();
            $table->string('load_unit')->nullable();
            $table->decimal('percent_of_reference', 6, 4)->nullable();
            $table->string('reference_lift')->nullable()->comment('personal_profile_entries の key（例: one_rm_bench）');
            $table->decimal('rpe_target', 3, 1)->nullable();
            $table->unsignedInteger('rest_seconds')->nullable();
            $table->string('side')->nullable();
            $table->string('tempo')->nullable();
            $table->text('cues')->nullable();
            $table->string('required_level');
            $table->string('progression_mode')->default('fixed');
            $table->json('alternates')->nullable();
            $table->text('abort_condition')->nullable();
            $table->text('completion_condition')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['program_day_step_id', 'sort_order']);
        });

        Schema::create('program_week_item_prescriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('program_week_id')->constrained('program_weeks')->cascadeOnDelete();
            $table->foreignUlid('program_step_item_id')->constrained('program_step_items')->cascadeOnDelete();
            $table->decimal('percent_of_reference', 6, 4)->nullable();
            $table->decimal('fixed_load', 8, 2)->nullable();
            $table->unsignedInteger('sets')->nullable();
            $table->unsignedInteger('reps')->nullable();
            $table->decimal('rpe_target', 3, 1)->nullable();
            $table->boolean('is_test')->default(false);
            $table->string('intent')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['program_week_id', 'program_step_item_id'], 'pwip_week_item_unique');
        });

        Schema::create('program_constraints', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('program_version_id')->constrained('program_versions')->cascadeOnDelete();
            $table->string('key');
            $table->string('kind')->default('program_rule');
            $table->text('description');
            $table->json('params')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['program_version_id', 'key']);
        });

        Schema::create('program_metric_targets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('program_version_id')->constrained('program_versions')->cascadeOnDelete();
            $table->foreignUlid('metric_id')->constrained('metrics')->restrictOnDelete();
            $table->decimal('target_value', 10, 2)->nullable();
            $table->decimal('target_low', 10, 2)->nullable();
            $table->decimal('target_high', 10, 2)->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['program_version_id', 'metric_id']);
        });

        Schema::create('program_attachments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('program_version_id')->constrained('program_versions')->cascadeOnDelete();
            $table->string('title');
            $table->string('disk');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('byte_size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_attachments');
        Schema::dropIfExists('program_metric_targets');
        Schema::dropIfExists('program_constraints');
        Schema::dropIfExists('program_week_item_prescriptions');
        Schema::dropIfExists('program_step_items');
        Schema::dropIfExists('program_day_steps');
        Schema::dropIfExists('program_choice_options');
        Schema::dropIfExists('program_choice_groups');
        Schema::dropIfExists('program_day_templates');
        Schema::dropIfExists('program_weeks');
        Schema::dropIfExists('program_phases');
        Schema::dropIfExists('program_versions');
        Schema::dropIfExists('programs');
    }
};
