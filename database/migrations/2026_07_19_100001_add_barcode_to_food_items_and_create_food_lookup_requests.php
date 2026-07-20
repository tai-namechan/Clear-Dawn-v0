<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PR-F1: 食事バーコード検索（設計 ai-features-implementation-plan.md §13 / completion-design §3）。
 * food_items にバーコード列（正規化値 + 種別）を追加し、
 * 外部照合（Open Food Facts / 将来の成分表OCR）の非同期リクエストを food_lookup_requests に持つ。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_items', function (Blueprint $table) {
            // 正規化済みバーコード（UPC-A は先頭0埋めで EAN-13 に揃える。最大14桁を許容）
            $table->string('barcode', 14)->nullable()->after('carb_g');
            $table->string('barcode_type', 8)->nullable()->after('barcode');

            $table->unique(['user_id', 'barcode'], 'food_items_user_barcode_unique');
        });

        Schema::create('food_lookup_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('barcode', 14);
            $table->string('barcode_type', 8);
            $table->string('status', 16)->default('pending');
            $table->string('source', 24)->nullable();
            $table->json('result')->nullable();
            $table->string('error_code', 64)->nullable();
            // F2（成分表OCR）で使用。成功・失敗・期限切れで削除する一時画像のパス
            $table->string('temp_image_path')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'barcode']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_lookup_requests');

        Schema::table('food_items', function (Blueprint $table) {
            $table->dropUnique('food_items_user_barcode_unique');
            $table->dropColumn(['barcode', 'barcode_type']);
        });
    }
};
