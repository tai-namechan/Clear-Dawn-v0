<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('food_item_id')->nullable()->constrained('food_items')->nullOnDelete();
            $table->date('eaten_on');
            $table->string('meal_type');
            $table->string('name');
            $table->decimal('quantity', 8, 2);
            $table->decimal('kcal', 8, 2);
            $table->decimal('protein_g', 8, 2);
            $table->decimal('fat_g', 8, 2);
            $table->decimal('carb_g', 8, 2);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'eaten_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_entries');
    }
};
