<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_plans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('life_area_id')->nullable()->constrained('life_areas')->nullOnDelete();
            $table->foreignUlid('routine_id')->nullable()->constrained('routines')->nullOnDelete();
            $table->string('title');
            $table->date('scheduled_on');
            $table->string('status');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'scheduled_on']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_plans');
    }
};
