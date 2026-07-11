<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connectors', function (Blueprint $table) {
            $table->string('external_account_id')->nullable()->after('source_type');
            $table->string('external_account_email')->nullable()->after('external_account_id');
            $table->text('access_token')->nullable()->after('external_account_email');
            $table->text('refresh_token')->nullable()->after('access_token');
            $table->timestamp('token_expires_at')->nullable()->after('refresh_token');
            $table->json('scopes')->nullable()->after('token_expires_at');
            $table->timestamp('last_sync_attempt_at')->nullable()->after('status');
            $table->string('last_error_code')->nullable()->after('last_synced_at');
            $table->timestamp('last_error_at')->nullable()->after('last_error_code');

            $table->unique(['user_id', 'source_type']);
        });
    }

    public function down(): void
    {
        Schema::table('connectors', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'source_type']);
            $table->dropColumn([
                'external_account_id',
                'external_account_email',
                'access_token',
                'refresh_token',
                'token_expires_at',
                'scopes',
                'last_sync_attempt_at',
                'last_error_code',
                'last_error_at',
            ]);
        });
    }
};
