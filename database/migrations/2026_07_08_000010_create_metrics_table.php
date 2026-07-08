<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metrics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('unit');
            $table->string('value_type');
            $table->unsignedInteger('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metrics');
    }
};
