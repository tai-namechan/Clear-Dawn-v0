<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yoyu_money_credit_cards', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('issuer_name')->nullable();
            $table->string('identifier_last4', 4)->nullable();
            $table->string('currency_code')->default('JPY');
            $table->string('closing_day');
            $table->string('payment_day');
            $table->unsignedTinyInteger('payment_month_offset')->default(1);
            $table->ulid('payment_account_id')->nullable();
            $table->bigInteger('limit_minor')->nullable();
            $table->bigInteger('available_minor')->nullable();
            $table->bigInteger('current_statement_minor')->nullable();
            $table->bigInteger('unconfirmed_minor')->nullable();
            $table->bigInteger('revolving_balance_minor')->nullable();
            $table->bigInteger('installment_balance_minor')->nullable();
            $table->unsignedInteger('revolving_fee_rate_bps')->nullable();
            $table->bigInteger('minimum_payment_minor')->nullable();
            $table->string('default_payment_type')->default('lump_sum');
            $table->timestamp('snapshot_as_of')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        Schema::create('yoyu_money_card_snapshots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('credit_card_id')->constrained('yoyu_money_credit_cards')->cascadeOnDelete();
            $table->timestamp('observed_at');
            $table->bigInteger('limit_minor')->nullable();
            $table->bigInteger('available_minor')->nullable();
            $table->bigInteger('current_statement_minor')->nullable();
            $table->bigInteger('unconfirmed_minor')->nullable();
            $table->bigInteger('revolving_balance_minor')->nullable();
            $table->bigInteger('installment_balance_minor')->nullable();
            $table->bigInteger('minimum_payment_minor')->nullable();
            $table->string('source');
            $table->timestamps();

            $table->index(['credit_card_id', 'observed_at']);
        });

        Schema::create('yoyu_money_card_statements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('credit_card_id')->constrained('yoyu_money_credit_cards')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('closed_on')->nullable();
            $table->date('due_on');
            $table->bigInteger('amount_minor');
            $table->string('status');
            $table->unsignedInteger('revision')->default(1);
            $table->ulid('cashflow_id')->nullable();
            $table->string('source');
            $table->ulid('supersedes_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['credit_card_id', 'period_end', 'revision']);
        });

        Schema::create('yoyu_money_loans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->ulid('lender_counterparty_id')->nullable();
            $table->string('currency_code')->default('JPY');
            $table->bigInteger('original_principal_minor')->nullable();
            $table->bigInteger('outstanding_principal_minor');
            $table->unsignedInteger('annual_interest_rate_bps')->nullable();
            $table->bigInteger('monthly_payment_minor');
            $table->bigInteger('minimum_payment_minor')->nullable();
            $table->date('next_payment_on');
            $table->date('maturity_on')->nullable();
            $table->boolean('prepayment_allowed')->default(true);
            $table->ulid('payment_account_id')->nullable();
            $table->string('status');
            $table->text('memo')->nullable();
            $table->timestamp('balance_as_of');
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('yoyu_money_loan_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('loan_id')->constrained('yoyu_money_loans')->cascadeOnDelete();
            $table->date('due_on');
            $table->ulid('cashflow_id')->nullable();
            $table->ulid('transaction_id')->nullable();
            $table->bigInteger('total_minor');
            $table->bigInteger('principal_minor')->nullable();
            $table->bigInteger('interest_minor')->nullable();
            $table->bigInteger('fee_minor')->nullable();
            $table->bigInteger('balance_after_minor')->nullable();
            $table->string('status');
            $table->string('source');
            $table->timestamps();

            $table->index(['loan_id', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yoyu_money_loan_payments');
        Schema::dropIfExists('yoyu_money_loans');
        Schema::dropIfExists('yoyu_money_card_statements');
        Schema::dropIfExists('yoyu_money_card_snapshots');
        Schema::dropIfExists('yoyu_money_credit_cards');
    }
};
