<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yoyu_money_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('currency_code')->default('JPY');
            $table->bigInteger('minimum_living_budget_minor')->nullable();
            $table->bigInteger('safety_buffer_minor')->nullable();
            $table->unsignedInteger('uncertain_outflow_reserve_bps')->default(10000);
            $table->boolean('include_expected_income')->default(false);
            $table->unsignedTinyInteger('calculation_horizon_months')->default(3);
            $table->string('formula_version')->default('1');
            $table->timestamps();

            $table->unique('user_id');
        });

        Schema::create('yoyu_money_categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->ulid('parent_id')->nullable();
            $table->string('name');
            $table->string('direction_scope');
            $table->string('flexibility_default');
            $table->string('cost_behavior_default')->nullable();
            $table->boolean('is_essential')->default(false);
            $table->string('color')->nullable();
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'is_active', 'sort_order']);
        });

        Schema::create('yoyu_money_counterparties', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('normalized_name');
            $table->string('kind');
            $table->text('memo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'normalized_name']);
        });

        Schema::create('yoyu_money_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('currency_code')->default('JPY');
            $table->bigInteger('current_balance_minor')->default(0);
            $table->bigInteger('available_balance_minor')->nullable();
            $table->timestamp('balance_as_of');
            $table->string('identifier_last4', 4)->nullable();
            $table->text('memo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        Schema::create('yoyu_money_account_balance_snapshots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('account_id')->constrained('yoyu_money_accounts')->cascadeOnDelete();
            $table->bigInteger('balance_minor');
            $table->bigInteger('available_balance_minor')->nullable();
            $table->timestamp('observed_at');
            $table->string('source');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'observed_at'], 'ym_bal_snaps_account_observed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yoyu_money_account_balance_snapshots');
        Schema::dropIfExists('yoyu_money_accounts');
        Schema::dropIfExists('yoyu_money_counterparties');
        Schema::dropIfExists('yoyu_money_categories');
        Schema::dropIfExists('yoyu_money_settings');
    }
};
