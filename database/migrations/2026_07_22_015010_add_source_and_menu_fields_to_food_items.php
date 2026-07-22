<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_items', function (Blueprint $table) {
            $table->string('source', 24)->nullable()->after('carb_g');
            $table->string('store_name', 100)->nullable()->after('barcode_type');
            $table->string('menu_name', 100)->nullable()->after('store_name');

            $table->index(['user_id', 'store_name', 'menu_name']);
        });
    }

    public function down(): void
    {
        Schema::table('food_items', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'store_name', 'menu_name']);
            $table->dropColumn(['source', 'store_name', 'menu_name']);
        });
    }
};
