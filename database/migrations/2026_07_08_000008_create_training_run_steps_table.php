<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_run_steps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('training_run_id')->constrained('training_runs')->cascadeOnDelete();
            $table->foreignUlid('exercise_id')->nullable()->constrained('exercises')->nullOnDelete();
            $table->string('exercise_name');
            $table->foreignUlid('video_id')->nullable()->constrained('videos')->nullOnDelete();
            $table->string('purpose')->nullable();
            $table->unsignedInteger('sort_order');
            $table->unsignedInteger('target_sets')->nullable();
            $table->unsignedInteger('target_reps')->nullable();
            $table->decimal('target_weight_kg', 6, 2)->nullable();
            $table->decimal('target_distance_m', 7, 2)->nullable();
            $table->unsignedInteger('target_duration_seconds')->nullable();
            $table->unsignedInteger('rest_seconds')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('actual_duration_seconds')->nullable();
            $table->text('memo')->nullable();
            $table->timestamps();

            $table->index(['training_run_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_run_steps');
    }
};
