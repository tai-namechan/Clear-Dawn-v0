<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->foreignUlid('exercise_id')->nullable()->after('life_area_id')->constrained('exercises')->nullOnDelete();
            $table->index(['user_id', 'exercise_id']);
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropForeign(['exercise_id']);
            $table->dropIndex(['user_id', 'exercise_id']);
            $table->dropColumn('exercise_id');
        });
    }
};
