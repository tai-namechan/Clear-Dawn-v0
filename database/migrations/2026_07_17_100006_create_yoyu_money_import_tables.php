<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yoyu_money_import_profiles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('source_format')->default('csv');
            $table->string('encoding')->nullable();
            $table->string('delimiter', 8)->nullable();
            $table->boolean('has_header')->default(true);
            $table->json('column_mapping');
            $table->json('transform_rules')->nullable();
            $table->json('mapping_config')->nullable();
            $table->ulid('default_account_id')->nullable();
            $table->string('default_currency_code')->default('JPY');
            $table->string('default_direction')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        Schema::create('yoyu_money_imports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('import_profile_id')
                ->nullable()
                ->constrained('yoyu_money_import_profiles')
                ->nullOnDelete();
            $table->ulid('account_id')->nullable();
            $table->string('status');
            $table->string('source_filename');
            $table->string('source_storage_path')->nullable();
            $table->string('source_checksum')->nullable();
            $table->string('idempotency_key');
            $table->json('mapping_config')->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('accepted_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('yoyu_money_import_rows', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('import_id')->constrained('yoyu_money_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw_payload')->nullable();
            $table->json('normalized_payload')->nullable();
            $table->string('status');
            $table->json('issue_codes')->nullable();
            $table->ulid('transaction_id')->nullable();
            $table->ulid('duplicate_of_transaction_id')->nullable();
            $table->timestamps();

            $table->unique(['import_id', 'row_number']);
            $table->index(['import_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yoyu_money_import_rows');
        Schema::dropIfExists('yoyu_money_imports');
        Schema::dropIfExists('yoyu_money_import_profiles');
    }
};
