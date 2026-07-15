<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Concierge letters (docs/product/kioku-final-remaining-implementation.md
     * §11.1): opening a letter for the first time records when its shown
     * memories were last surfaced, feeding the 14-day candidate cooldown.
     */
    public function up(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            $table->timestamp('last_referenced_at')->nullable()->after('referenced_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memories', function (Blueprint $table) {
            $table->dropColumn('last_referenced_at');
        });
    }
};
