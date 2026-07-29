<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Embedding stage state. Migration does not call external AI.
 * down() drops the table — prefer forward fix over production rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memory_embeddings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('memory_id')->constrained('memories')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('model', 128);
            $table->unsignedInteger('dimensions')->default(0);
            $table->string('schema_version', 32);
            $table->string('content_hash', 64);
            $table->longText('vector')->nullable();
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('input_tokens')->nullable();
            $table->decimal('actual_usd', 12, 6)->nullable();
            $table->timestamp('embedded_at')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['memory_id', 'provider', 'model', 'schema_version'],
                'memory_embeddings_current_unique',
            );
            $table->index(['user_id', 'status'], 'memory_embeddings_user_status_index');
            $table->index(['user_id', 'content_hash'], 'memory_embeddings_user_hash_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_embeddings');
    }
};
