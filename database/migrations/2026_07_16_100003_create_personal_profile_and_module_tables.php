<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 個人プロファイル（有効日つき履歴）とモジュール設定（ADR-0010/0011）。
 * 値は import コマンドで投入し、リポジトリには含めない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_profile_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->decimal('value_numeric', 10, 3)->nullable();
            $table->text('value_text')->nullable();
            $table->json('value_json')->nullable();
            $table->string('unit')->nullable();
            $table->date('effective_from');
            $table->string('source')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'key', 'effective_from']);
        });

        Schema::create('user_module_settings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('module_key');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'module_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_module_settings');
        Schema::dropIfExists('personal_profile_entries');
    }
};
