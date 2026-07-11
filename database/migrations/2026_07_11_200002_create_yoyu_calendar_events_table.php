<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yoyu_calendar_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('connector_id')->constrained('connectors')->cascadeOnDelete();
            $table->string('calendar_external_id')->default('primary');
            $table->string('external_id');
            $table->string('i_cal_uid')->nullable();
            $table->string('title');
            // Timed events: UTC timestamps. All-day events: local dates (end exclusive).
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('event_timezone')->nullable();
            $table->boolean('all_day')->default(false);
            $table->string('transparency')->default('opaque');
            $table->string('status')->default('confirmed');
            $table->string('location')->nullable();
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique(['connector_id', 'calendar_external_id', 'external_id'], 'yoyu_calendar_events_source_unique');
            $table->index(['user_id', 'starts_at']);
            $table->index(['user_id', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yoyu_calendar_events');
    }
};
