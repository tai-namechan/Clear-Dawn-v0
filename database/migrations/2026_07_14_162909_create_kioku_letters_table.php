<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Weekly concierge letter (docs/product/kioku-final-remaining-
     * implementation.md §11.2). One letter per user per week; the character
     * variant is fixed at creation and only affects presentation.
     */
    public function up(): void
    {
        Schema::create('kioku_letters', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->string('status')->default('generating');
            $table->string('character_variant');
            $table->text('intro')->nullable();
            $table->text('context')->nullable();
            $table->unsignedInteger('candidate_count')->default(0);
            $table->unsignedTinyInteger('item_count')->default(0);
            $table->string('prompt_key');
            $table->string('model')->nullable();
            $table->json('generation_meta')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignUlid('evaluation_memory_id')->nullable()->constrained('memories')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'week_start']);
            $table->index(['user_id', 'status', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kioku_letters');
    }
};
