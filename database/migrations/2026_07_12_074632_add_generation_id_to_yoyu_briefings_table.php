<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yoyu_briefings', function (Blueprint $table) {
            $table->ulid('generation_id')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('yoyu_briefings', function (Blueprint $table) {
            $table->dropColumn('generation_id');
        });
    }
};
