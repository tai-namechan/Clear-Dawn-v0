<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yoyu_money_monthly_snapshots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('year_month', 7);
            $table->unsignedInteger('revision')->default(1);
            $table->string('status');
            $table->string('formula_version')->default('1');
            $table->date('as_of_date');
            $table->string('currency_code')->default('JPY');
            $table->json('balances_payload')->nullable();
            $table->json('cashflows_payload')->nullable();
            $table->json('margin_payload')->nullable();
            $table->json('assumptions_payload')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->ulid('supersedes_id')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'year_month', 'revision']);
            $table->index(['user_id', 'year_month', 'status']);
        });

        Schema::create('yoyu_money_simulations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status');
            $table->date('base_date');
            $table->unsignedTinyInteger('horizon_months')->default(3);
            $table->string('formula_version')->default('1');
            $table->string('currency_code')->default('JPY');
            $table->json('assumptions_payload')->nullable();
            $table->json('baseline_payload')->nullable();
            $table->json('result_payload')->nullable();
            $table->text('memo')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('yoyu_money_simulation_actions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('simulation_id')->constrained('yoyu_money_simulations')->cascadeOnDelete();
            $table->string('action_type');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('target_type')->nullable();
            $table->ulid('target_id')->nullable();
            $table->json('params_payload')->nullable();
            $table->json('effect_payload')->nullable();
            $table->timestamps();

            $table->index(['simulation_id', 'sort_order']);
        });

        Schema::create('yoyu_money_decisions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('decided_on');
            $table->string('status');
            $table->foreignUlid('simulation_id')
                ->nullable()
                ->constrained('yoyu_money_simulations')
                ->nullOnDelete();
            $table->json('before_payload')->nullable();
            $table->json('expected_effect_payload')->nullable();
            $table->json('actual_effect_payload')->nullable();
            $table->text('memo')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'decided_on']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('yoyu_money_decision_links', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('decision_id')->constrained('yoyu_money_decisions')->cascadeOnDelete();
            $table->string('subject_type');
            $table->ulid('subject_id');
            $table->string('relation_type');
            $table->timestamps();

            $table->unique(['decision_id', 'subject_type', 'subject_id']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yoyu_money_decision_links');
        Schema::dropIfExists('yoyu_money_decisions');
        Schema::dropIfExists('yoyu_money_simulation_actions');
        Schema::dropIfExists('yoyu_money_simulations');
        Schema::dropIfExists('yoyu_money_monthly_snapshots');
    }
};
