<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_set_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('training_run_step_id')->constrained('training_run_steps')->cascadeOnDelete();
            $table->unsignedInteger('set_number');
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->unsignedInteger('reps')->nullable();
            $table->decimal('distance_m', 7, 2)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('memo')->nullable();
            $table->timestamps();

            $table->unique(['training_run_step_id', 'set_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_set_logs');
    }
};
