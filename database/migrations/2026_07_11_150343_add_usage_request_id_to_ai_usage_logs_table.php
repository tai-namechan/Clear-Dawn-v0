<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->foreignUlid('usage_request_id')
                ->nullable()
                ->after('id')
                ->constrained('ai_usage_requests')
                ->nullOnDelete();

            $table->unique('usage_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->dropUnique(['usage_request_id']);
            $table->dropConstrainedForeignId('usage_request_id');
        });
    }
};
