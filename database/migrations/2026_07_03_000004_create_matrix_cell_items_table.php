<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('matrix_cell_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('matrix_cell_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('memo')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->dateTime('completed_at')->nullable();
            $table->integer('sort_order');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['matrix_cell_id', 'sort_order']);
            $table->index(['matrix_cell_id', 'is_completed']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matrix_cell_items');
    }
};
