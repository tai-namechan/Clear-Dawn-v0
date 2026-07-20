<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yoyu_money_recurring_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('direction');
            $table->string('cashflow_kind');
            $table->bigInteger('amount_minor');
            $table->string('currency_code')->default('JPY');
            $table->string('frequency');
            $table->unsignedInteger('interval_count')->default(1);
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->unsignedTinyInteger('month_of_year')->nullable();
            $table->date('start_on');
            $table->date('end_on')->nullable();
            $table->string('timezone', 64);
            $table->ulid('category_id')->nullable();
            $table->ulid('counterparty_id')->nullable();
            $table->ulid('settlement_account_id')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('income_amount_basis')->nullable();
            $table->string('cost_behavior')->nullable();
            $table->string('certainty');
            $table->string('flexibility');
            $table->string('priority');
            $table->boolean('is_active')->default(true);
            $table->date('generated_through')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active', 'generated_through'], 'ym_recur_rules_active_gen_through_idx');
        });

        Schema::create('yoyu_money_cashflows', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('direction');
            $table->string('kind');
            $table->string('name');
            $table->bigInteger('amount_minor');
            $table->string('currency_code')->default('JPY');
            $table->date('due_on');
            $table->date('original_due_on')->nullable();
            $table->string('status');
            $table->string('certainty');
            $table->ulid('category_id')->nullable();
            $table->ulid('counterparty_id')->nullable();
            $table->ulid('settlement_account_id')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('income_amount_basis')->nullable();
            $table->string('cost_behavior')->nullable();
            $table->ulid('recurring_rule_id')->nullable();
            $table->date('occurrence_on')->nullable();
            $table->ulid('supersedes_id')->nullable();
            $table->string('flexibility');
            $table->string('priority');
            $table->text('memo')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(['recurring_rule_id', 'occurrence_on']);
            $table->index(['user_id', 'due_on', 'status']);
            $table->index(['user_id', 'direction', 'certainty']);
        });

        Schema::create('yoyu_money_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->ulid('account_id')->nullable();
            $table->string('direction');
            $table->string('kind');
            $table->bigInteger('amount_minor');
            $table->string('currency_code')->default('JPY');
            $table->date('occurred_on');
            $table->date('posted_on')->nullable();
            $table->string('description_raw');
            $table->string('description_normalized');
            $table->ulid('category_id')->nullable();
            $table->ulid('counterparty_id')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('card_payment_type')->nullable();
            $table->string('cost_behavior')->nullable();
            $table->string('status')->default('posted');
            $table->string('source');
            $table->string('source_provider')->nullable();
            $table->string('external_id')->nullable();
            $table->ulid('import_id')->nullable();
            $table->ulid('import_row_id')->nullable();
            $table->ulid('transfer_group_id')->nullable();
            $table->text('memo')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'occurred_on']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('yoyu_money_reconciliations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('cashflow_id')->constrained('yoyu_money_cashflows')->cascadeOnDelete();
            $table->foreignUlid('transaction_id')->constrained('yoyu_money_transactions')->cascadeOnDelete();
            $table->bigInteger('amount_minor');
            $table->timestamp('reconciled_at');
            $table->string('source');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['cashflow_id', 'transaction_id']);
        });

        Schema::create('yoyu_money_audit_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type');
            $table->string('subject_type');
            $table->ulid('subject_id');
            $table->json('before_payload')->nullable();
            $table->json('after_payload')->nullable();
            $table->string('correlation_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['user_id', 'occurred_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yoyu_money_audit_events');
        Schema::dropIfExists('yoyu_money_reconciliations');
        Schema::dropIfExists('yoyu_money_transactions');
        Schema::dropIfExists('yoyu_money_cashflows');
        Schema::dropIfExists('yoyu_money_recurring_rules');
    }
};
