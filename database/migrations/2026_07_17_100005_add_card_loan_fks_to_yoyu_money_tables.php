<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yoyu_money_recurring_rules', function (Blueprint $table) {
            $table->foreignUlid('credit_card_id')
                ->nullable()
                ->after('settlement_account_id')
                ->constrained('yoyu_money_credit_cards')
                ->nullOnDelete();
        });

        Schema::table('yoyu_money_recurring_rules', function (Blueprint $table) {
            $table->foreignUlid('loan_id')
                ->nullable()
                ->after('credit_card_id')
                ->constrained('yoyu_money_loans')
                ->nullOnDelete();
        });

        Schema::table('yoyu_money_cashflows', function (Blueprint $table) {
            $table->foreignUlid('credit_card_id')
                ->nullable()
                ->after('settlement_account_id')
                ->constrained('yoyu_money_credit_cards')
                ->nullOnDelete();
        });

        Schema::table('yoyu_money_cashflows', function (Blueprint $table) {
            $table->foreignUlid('loan_id')
                ->nullable()
                ->after('credit_card_id')
                ->constrained('yoyu_money_loans')
                ->nullOnDelete();
        });

        Schema::table('yoyu_money_transactions', function (Blueprint $table) {
            $table->foreignUlid('credit_card_id')
                ->nullable()
                ->after('counterparty_id')
                ->constrained('yoyu_money_credit_cards')
                ->nullOnDelete();
        });

        Schema::table('yoyu_money_transactions', function (Blueprint $table) {
            $table->foreignUlid('loan_id')
                ->nullable()
                ->after('credit_card_id')
                ->constrained('yoyu_money_loans')
                ->nullOnDelete();
        });

        Schema::table('yoyu_money_transactions', function (Blueprint $table) {
            $table->foreignUlid('card_statement_id')
                ->nullable()
                ->after('loan_id')
                ->constrained('yoyu_money_card_statements')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('yoyu_money_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('card_statement_id');
        });

        Schema::table('yoyu_money_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loan_id');
        });

        Schema::table('yoyu_money_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credit_card_id');
        });

        Schema::table('yoyu_money_cashflows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loan_id');
        });

        Schema::table('yoyu_money_cashflows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credit_card_id');
        });

        Schema::table('yoyu_money_recurring_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loan_id');
        });

        Schema::table('yoyu_money_recurring_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credit_card_id');
        });
    }
};
