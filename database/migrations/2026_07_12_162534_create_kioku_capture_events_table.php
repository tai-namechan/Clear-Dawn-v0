<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Minimal capture funnel metrics (docs/product/kioku-quick-capture.md §13).
     * Never stores raw content, transcripts, or audio.
     */
    public function up(): void
    {
        Schema::create('kioku_capture_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->string('source_type');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('retry_count')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'event']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kioku_capture_events');
    }
};
