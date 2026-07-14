<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Original media for memories (docs/product/kioku-quick-capture.md §5).
     * For voice memories the audio_original asset is the canonical raw; the
     * file lives on a private disk and only its disk/path are stored here.
     */
    public function up(): void
    {
        Schema::create('memory_assets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('memory_id')->constrained('memories')->cascadeOnDelete();
            $table->string('kind');
            $table->string('disk');
            $table->string('path');
            $table->string('mime_type');
            $table->unsignedBigInteger('byte_size');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('checksum')->nullable();
            $table->timestamps();

            $table->index(['memory_id', 'kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memory_assets');
    }
};
