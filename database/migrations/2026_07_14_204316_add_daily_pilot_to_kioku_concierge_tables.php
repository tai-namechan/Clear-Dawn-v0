<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase C daily pilot fields (docs/product/kioku-concierge-daily-pilot.md).
 * Forward-only: does not rewrite the original create_kioku_letters migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            $table->timestamp('last_delivered_at')->nullable()->after('last_referenced_at');
        });

        Schema::table('kioku_letters', function (Blueprint $table) {
            $table->string('mode')->default('live')->after('week_start');
            $table->string('cadence')->default('weekly')->after('mode');
            $table->date('delivery_date')->nullable()->after('cadence');
            $table->string('dedupe_key')->nullable()->after('delivery_date');
            $table->unsignedTinyInteger('pilot_day')->nullable()->after('dedupe_key');
            $table->unsignedTinyInteger('retry_count')->default(0)->after('generation_meta');
            $table->timestamp('halted_at')->nullable()->after('completed_at');
            $table->timestamp('halt_resolved_at')->nullable()->after('halted_at');
            $table->text('halt_resolution_note')->nullable()->after('halt_resolved_at');
            $table->timestamp('test_expires_at')->nullable()->after('halt_resolution_note');
        });

        // Backfill existing weekly letters before unique(user_id, dedupe_key).
        DB::table('kioku_letters')
            ->whereNull('dedupe_key')
            ->orderBy('id')
            ->chunkById(100, function ($letters): void {
                foreach ($letters as $letter) {
                    $weekStart = (string) $letter->week_start;
                    DB::table('kioku_letters')
                        ->where('id', $letter->id)
                        ->update([
                            'mode' => 'live',
                            'cadence' => 'weekly',
                            'delivery_date' => $weekStart,
                            'dedupe_key' => 'weekly:'.$weekStart,
                        ]);
                }
            });

        Schema::table('kioku_letters', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'week_start']);
        });

        Schema::table('kioku_letters', function (Blueprint $table) {
            $table->unique(['user_id', 'dedupe_key']);
            $table->index(['user_id', 'mode', 'cadence', 'delivery_date'], 'kioku_letters_user_mode_cadence_delivery_idx');
        });
    }

    public function down(): void
    {
        Schema::table('kioku_letters', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'dedupe_key']);
            $table->dropIndex('kioku_letters_user_mode_cadence_delivery_idx');
        });

        Schema::table('kioku_letters', function (Blueprint $table) {
            $table->dropColumn([
                'mode',
                'cadence',
                'delivery_date',
                'dedupe_key',
                'pilot_day',
                'retry_count',
                'halted_at',
                'halt_resolved_at',
                'halt_resolution_note',
                'test_expires_at',
            ]);
        });

        Schema::table('kioku_letters', function (Blueprint $table) {
            $table->unique(['user_id', 'week_start']);
        });

        Schema::table('memories', function (Blueprint $table) {
            $table->dropColumn('last_delivered_at');
        });
    }
};
