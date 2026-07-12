<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yoyu_briefings', function (Blueprint $table) {
            $table->json('structured_data')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('yoyu_briefings', function (Blueprint $table) {
            $table->dropColumn('structured_data');
        });
    }
};
