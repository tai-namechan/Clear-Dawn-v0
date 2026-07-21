<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PR-F2 入口2（設計 §13.4 / 完成設計 §3）: バーコードが無い・読めない商品の
 * 成分表直接登録を許すため、barcode / barcode_type を nullable 化する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_lookup_requests', function (Blueprint $table) {
            $table->string('barcode', 14)->nullable()->change();
            $table->string('barcode_type', 8)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('food_lookup_requests', function (Blueprint $table) {
            $table->string('barcode', 14)->nullable(false)->change();
            $table->string('barcode_type', 8)->nullable(false)->change();
        });
    }
};
