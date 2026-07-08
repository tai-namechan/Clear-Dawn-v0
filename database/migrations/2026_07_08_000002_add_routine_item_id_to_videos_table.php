<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->foreignUlid('routine_item_id')->nullable()->after('life_area_id')->constrained('routine_items')->nullOnDelete();
            $table->index(['user_id', 'routine_item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropForeign(['routine_item_id']);
            $table->dropIndex(['user_id', 'routine_item_id']);
            $table->dropColumn('routine_item_id');
        });
    }
};
