<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quick capture (docs/product/kioku-quick-capture.md):
     * - client_capture_id: client-generated UUID, unique per user for idempotent resend
     * - raw_content nullable: voice memories keep the original audio asset as canonical raw
     * - transcript_text: derived text from audio, regenerable
     * - transcription_status: voice-only pipeline state (null for manual/url)
     */
    public function up(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            $table->uuid('client_capture_id')->nullable()->after('user_id');
            $table->text('raw_content')->nullable()->change();
            $table->text('transcript_text')->nullable()->after('raw_content');
            $table->string('transcription_status')->nullable()->after('status');

            $table->unique(['user_id', 'client_capture_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'client_capture_id']);
            $table->dropColumn(['client_capture_id', 'transcript_text', 'transcription_status']);
            $table->text('raw_content')->nullable(false)->change();
        });
    }
};
