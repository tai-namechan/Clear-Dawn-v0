<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('period', 7);
            $table->string('feature');
            $table->string('model');
            $table->decimal('estimated_usd', 12, 6);
            $table->decimal('actual_usd', 12, 6)->nullable();
            $table->decimal('charged_usd', 12, 6)->nullable();
            $table->string('status');
            $table->timestamp('provider_started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('failure_code')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_requests');
    }
};
