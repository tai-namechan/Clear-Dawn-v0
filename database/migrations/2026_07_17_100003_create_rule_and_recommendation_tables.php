<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4: 決定論ルール・推奨・判断（ADR-0011）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rule_definitions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete()->comment('null = グローバル定義');
            $table->string('key');
            $table->string('kind');
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('params')->nullable();
            $table->text('evidence')->nullable();
            $table->text('population')->nullable();
            $table->text('limitations')->nullable();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->string('verified_by')->nullable();
            $table->unsignedInteger('version_number')->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_hard_gate')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'key', 'version_number']);
        });

        Schema::create('rule_evaluations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('rule_definition_id')->constrained('rule_definitions')->cascadeOnDelete();
            $table->date('evaluated_on');
            $table->boolean('triggered');
            $table->json('inputs_snapshot')->nullable();
            $table->json('outputs_snapshot')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'evaluated_on']);
        });

        Schema::create('recommendations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('rule_evaluation_id')->nullable()->constrained('rule_evaluations')->nullOnDelete();
            $table->date('recommended_on');
            $table->string('scope')->comment('A/B/C 承認段');
            $table->string('title');
            $table->text('rationale')->nullable();
            $table->text('goal_impact')->nullable();
            $table->json('plan_diff')->nullable();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->json('missing_data')->nullable();
            $table->boolean('is_interrupt')->default(false);
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['user_id', 'recommended_on', 'status']);
        });

        Schema::create('recommendation_options', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('recommendation_id')->constrained('recommendations')->cascadeOnDelete();
            $table->string('action_key');
            $table->string('label');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('recommendation_decisions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('recommendation_id')->constrained('recommendations')->cascadeOnDelete();
            $table->foreignUlid('recommendation_option_id')->nullable()->constrained('recommendation_options')->nullOnDelete();
            $table->string('action_key');
            $table->text('reason')->nullable();
            $table->json('result_snapshot')->nullable();
            $table->timestamps();

            $table->unique('recommendation_id');
        });

        Schema::create('outcome_evaluations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('recommendation_decision_id')->nullable()->constrained('recommendation_decisions')->nullOnDelete();
            $table->foreignUlid('routine_session_id')->nullable()->constrained('routine_sessions')->nullOnDelete();
            $table->date('evaluated_on');
            $table->string('outcome_key');
            $table->decimal('score', 5, 2)->nullable();
            $table->text('note')->nullable();
            $table->json('metrics_snapshot')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'evaluated_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outcome_evaluations');
        Schema::dropIfExists('recommendation_decisions');
        Schema::dropIfExists('recommendation_options');
        Schema::dropIfExists('recommendations');
        Schema::dropIfExists('rule_evaluations');
        Schema::dropIfExists('rule_definitions');
    }
};
