<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Items of a concierge letter (docs/product/kioku-final-remaining-
     * implementation.md §11.3). Snapshots keep the letter readable even if
     * the source memory is later re-enriched; verdict values are fixed by
     * app-level constants, never a DB enum.
     */
    public function up(): void
    {
        Schema::create('kioku_letter_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('letter_id')->constrained('kioku_letters')->cascadeOnDelete();
            $table->foreignUlid('memory_id')->constrained('memories')->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('title_snapshot');
            $table->text('summary_snapshot')->nullable();
            $table->string('headline');
            $table->string('why_now', 500);
            $table->json('related_memory_ids')->nullable();
            $table->string('verdict')->nullable();
            $table->text('verdict_note')->nullable();
            $table->timestamp('verdict_at')->nullable();
            $table->timestamps();

            $table->unique(['letter_id', 'position']);
            $table->unique(['letter_id', 'memory_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kioku_letter_items');
    }
};
