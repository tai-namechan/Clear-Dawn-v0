<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Note: On Postgres, prefer jsonb + GIN indexes for structured_data/tags.
     * Local sqlite / production MySQL use json without GIN.
     */
    public function up(): void
    {
        Schema::create('memories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_type');
            $table->string('memory_type')->nullable();
            $table->string('title');
            $table->text('raw_content');
            $table->text('summary')->nullable();
            $table->json('structured_data')->nullable(); // Postgres: jsonb + GIN
            $table->json('tags')->nullable(); // Postgres: jsonb + GIN
            $table->timestamp('captured_at');
            $table->unsignedTinyInteger('importance')->default(3);
            $table->boolean('sensitive')->default(false);
            $table->string('status')->default('captured');
            $table->unsignedInteger('referenced_count')->default(0);
            $table->timestamps();

            $table->index('user_id');
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'memory_type']);
            $table->index(['user_id', 'captured_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memories');
    }
};
