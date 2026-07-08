<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_plan_steps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('routine_plan_id')->constrained('routine_plans')->cascadeOnDelete();
            $table->foreignUlid('routine_item_id')->constrained('routine_items')->restrictOnDelete();
            $table->foreignUlid('video_id')->nullable()->constrained('videos')->nullOnDelete();
            $table->string('purpose')->nullable();
            $table->unsignedInteger('sort_order');
            $table->decimal('target_load', 8, 2)->nullable();
            $table->string('load_unit')->nullable();
            $table->decimal('target_amount', 8, 2)->nullable();
            $table->string('amount_unit')->nullable();
            $table->unsignedInteger('target_blocks')->nullable();
            $table->unsignedInteger('rest_seconds')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['routine_plan_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_plan_steps');
    }
};
