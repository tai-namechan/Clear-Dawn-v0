<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user daily pilot schedule (docs/product/kioku-concierge-daily-pilot.md).
 * Target users live in DB — never hard-code them in env.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kioku_concierge_schedules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('state')->default('inactive');
            $table->date('pilot_start_date')->nullable();
            $table->date('pilot_end_date')->nullable();
            $table->unsignedTinyInteger('pilot_days')->default(14);
            $table->string('timezone')->default('Asia/Tokyo');
            $table->string('daily_delivery_time')->default('21:00');
            $table->timestamp('next_delivery_at')->nullable();
            $table->unsignedTinyInteger('consecutive_unopened')->default(0);
            $table->text('pause_reason')->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['state', 'next_delivery_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kioku_concierge_schedules');
    }
};
