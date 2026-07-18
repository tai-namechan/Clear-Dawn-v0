<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('parent_goal_id')->nullable()->constrained('goals')->nullOnDelete();
            $table->foreignUlid('matrix_cell_id')->nullable()->constrained('matrix_cells')->nullOnDelete();
            $table->string('name');
            $table->text('why')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->string('status');
            $table->date('deadline')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('goal_metrics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('goal_id')->constrained('goals')->cascadeOnDelete();
            $table->foreignUlid('metric_id')->constrained('metrics')->restrictOnDelete();
            $table->decimal('baseline_value', 10, 2)->nullable();
            $table->decimal('target_value', 10, 2)->nullable();
            $table->decimal('target_low', 10, 2)->nullable();
            $table->decimal('target_high', 10, 2)->nullable();
            $table->string('direction')->nullable();
            $table->string('note')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['goal_id', 'metric_id']);
        });

        Schema::create('goal_change_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('goal_id')->constrained('goals')->cascadeOnDelete();
            $table->json('changes');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['goal_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_change_logs');
        Schema::dropIfExists('goal_metrics');
        Schema::dropIfExists('goals');
    }
};
