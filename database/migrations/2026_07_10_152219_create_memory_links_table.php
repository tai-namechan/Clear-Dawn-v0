<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memory_links', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('from_memory_id')->constrained('memories')->cascadeOnDelete();
            $table->foreignUlid('to_memory_id')->constrained('memories')->cascadeOnDelete();
            $table->string('kind');
            $table->float('score')->nullable();
            $table->string('created_by');
            $table->timestamps();

            $table->index(['from_memory_id', 'kind']);
            $table->unique(['from_memory_id', 'to_memory_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_links');
    }
};
