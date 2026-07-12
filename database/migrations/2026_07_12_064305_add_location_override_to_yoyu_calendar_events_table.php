<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * App-owned place label for events whose Google location is empty.
 * `location` remains provider-cache-only (written by SyncGoogleCalendarJob).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yoyu_calendar_events', function (Blueprint $table) {
            $table->string('location_override')->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('yoyu_calendar_events', function (Blueprint $table) {
            $table->dropColumn('location_override');
        });
    }
};
