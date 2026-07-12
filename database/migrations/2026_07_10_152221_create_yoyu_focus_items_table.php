<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yoyu_focus_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('memory_id')->constrained('memories')->cascadeOnDelete();
            $table->string('status')->default('open');
            $table->timestamp('snoozed_until')->nullable();
            $table->foreignUlid('converted_task_id')->nullable()->constrained('yoyu_tasks')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->unique(['user_id', 'memory_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yoyu_focus_items');
    }
};
