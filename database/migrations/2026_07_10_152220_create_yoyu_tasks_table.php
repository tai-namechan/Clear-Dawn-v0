<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yoyu_tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('status')->default('planned');
            $table->unsignedSmallInteger('estimate_minutes')->default(30);
            $table->date('due_date')->nullable();
            $table->date('planned_date')->nullable();
            $table->string('priority')->nullable();
            $table->string('category')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yoyu_tasks');
    }
};
