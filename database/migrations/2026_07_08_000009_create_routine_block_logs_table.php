<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_block_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('routine_session_step_id')->constrained('routine_session_steps')->cascadeOnDelete();
            $table->unsignedInteger('block_number');
            $table->decimal('load_value', 8, 2)->nullable();
            $table->string('load_unit')->nullable();
            $table->decimal('amount_value', 8, 2)->nullable();
            $table->string('amount_unit')->nullable();
            $table->string('memo')->nullable();
            $table->timestamps();

            $table->unique(['routine_session_step_id', 'block_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_block_logs');
    }
};
