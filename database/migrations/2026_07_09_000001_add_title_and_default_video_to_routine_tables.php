<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routine_items', function (Blueprint $table) {
            $table->foreignUlid('default_video_id')
                ->nullable()
                ->after('default_amount_unit')
                ->constrained('videos')
                ->nullOnDelete();
        });

        Schema::table('routine_steps', function (Blueprint $table) {
            $table->string('title')->nullable()->after('routine_item_id');
        });

        Schema::table('routine_plan_steps', function (Blueprint $table) {
            $table->string('title')->nullable()->after('routine_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('routine_plan_steps', function (Blueprint $table) {
            $table->dropColumn('title');
        });

        Schema::table('routine_steps', function (Blueprint $table) {
            $table->dropColumn('title');
        });

        Schema::table('routine_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_video_id');
        });
    }
};
