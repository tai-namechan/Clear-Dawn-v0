<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kioku_recall_feedback', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('search_session_id');
            $table->string('query_hash', 64);
            $table->foreignUlid('memory_id')->nullable()->constrained('memories')->nullOnDelete();
            $table->unsignedSmallInteger('shown_rank')->nullable();
            $table->unsignedSmallInteger('tag_rank')->nullable();
            $table->unsignedSmallInteger('fulltext_rank')->nullable();
            $table->unsignedSmallInteger('vector_rank')->nullable();
            $table->decimal('final_score', 10, 6)->nullable();
            $table->string('verdict', 32);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'memory_id', 'verdict']);
            $table->index(['user_id', 'query_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kioku_recall_feedback');
    }
};
