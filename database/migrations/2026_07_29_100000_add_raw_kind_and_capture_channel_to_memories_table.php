<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Separates raw media kind from capture ingress channel.
 * Columns stay nullable until backfill completes; new captures fill both.
 *
 * Production: MySQL 8.4. Local tests: SQLite.
 * down() drops columns — prefer forward fix over production rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            $table->string('raw_kind', 32)->nullable()->after('source_type');
            $table->string('capture_channel', 64)->nullable()->after('raw_kind');
        });

        Schema::table('memories', function (Blueprint $table) {
            $table->index(['user_id', 'raw_kind', 'status'], 'memories_user_raw_kind_status_index');
            $table->index(['user_id', 'capture_channel'], 'memories_user_capture_channel_index');
        });
    }

    public function down(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            $table->dropIndex('memories_user_raw_kind_status_index');
            $table->dropIndex('memories_user_capture_channel_index');
        });

        Schema::table('memories', function (Blueprint $table) {
            $table->dropColumn(['raw_kind', 'capture_channel']);
        });
    }
};
