<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user prep/buffer minutes for travel lead (defaults match YoyuTravelConstants).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yoyu_preferences', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('prep_minutes')->default(10);
            $table->unsignedTinyInteger('buffer_minutes')->default(5);
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yoyu_preferences');
    }
};
