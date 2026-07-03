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
        Schema::create('matrix_cells', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Life Area の物理削除はアカウント削除カスケード時のみ発生するため、
            // restrict ではなく cascade にして users → life_areas → matrix_cells の削除順序に依存しない
            $table->foreignUlid('life_area_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('matrix_row_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'life_area_id', 'matrix_row_id']);
            $table->index('user_id');
            $table->index('life_area_id');
            $table->index('matrix_row_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matrix_cells');
    }
};
