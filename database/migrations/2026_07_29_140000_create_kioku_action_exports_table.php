<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kioku_action_exports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('memory_id')->constrained('memories')->cascadeOnDelete();
            $table->string('target', 64);
            $table->string('target_id', 64)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'memory_id', 'target'], 'kioku_action_exports_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kioku_action_exports');
    }
};
