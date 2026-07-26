<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_programs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('team_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('visibility_status')->default('draft')->index();
            $table->text('summary')->nullable();
            $table->timestamps();
            $table->index(['team_id', 'visibility_status']);
        });

        Schema::create('team_program_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('team_program_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['team_program_id', 'sort_order']);
        });

        Schema::create('team_program_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('team_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('assigned')->index();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
            $table->unique(['team_program_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_program_assignments');
        Schema::dropIfExists('team_program_items');
        Schema::dropIfExists('team_programs');
    }
};
