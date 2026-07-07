<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metric_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('metric_id')->constrained('metrics');
            $table->foreignUlid('life_area_id')->nullable()->constrained('life_areas')->nullOnDelete();
            $table->date('recorded_on');
            $table->decimal('value', 8, 2);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'metric_id', 'recorded_on']);
            $table->index(['user_id', 'metric_id', 'recorded_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metric_records');
    }
};
