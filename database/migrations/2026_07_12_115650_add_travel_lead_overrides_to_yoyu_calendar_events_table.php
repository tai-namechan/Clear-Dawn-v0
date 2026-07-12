<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App-owned per-event prep/buffer overrides.
 * NULL = fall back to yoyu_preferences (then YoyuTravelConstants).
 * SyncGoogleCalendarJob must not write these columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yoyu_calendar_events', function (Blueprint $table) {
            $table->unsignedTinyInteger('prep_minutes_override')->nullable()->after('location_override');
            $table->unsignedTinyInteger('buffer_minutes_override')->nullable()->after('prep_minutes_override');
        });
    }

    public function down(): void
    {
        Schema::table('yoyu_calendar_events', function (Blueprint $table) {
            $table->dropColumn(['prep_minutes_override', 'buffer_minutes_override']);
        });
    }
};
