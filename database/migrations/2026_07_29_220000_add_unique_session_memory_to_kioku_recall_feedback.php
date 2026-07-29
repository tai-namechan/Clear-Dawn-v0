<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kioku_recall_feedback', function (Blueprint $table) {
            $table->unique(
                ['user_id', 'search_session_id', 'memory_id'],
                'kioku_recall_feedback_session_memory_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('kioku_recall_feedback', function (Blueprint $table) {
            $table->dropUnique('kioku_recall_feedback_session_memory_unique');
        });
    }
};
