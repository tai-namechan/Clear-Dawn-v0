<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('body_measurements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('measured_on');
            $table->string('input_source');
            $table->string('source_pdf_path')->nullable();
            $table->string('parse_status');
            $table->timestamp('confirmed_at')->nullable();
            $table->decimal('weight_kg', 8, 2)->nullable();
            $table->decimal('lean_body_mass_kg', 8, 2)->nullable();
            $table->decimal('skeletal_muscle_mass_kg', 8, 2)->nullable();
            $table->decimal('body_fat_percentage', 5, 2)->nullable();
            $table->decimal('abdominal_circumference_cm', 6, 2)->nullable();
            $table->json('raw_extracted_json')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'measured_on']);
            $table->index(['user_id', 'measured_on']);
        });

        Schema::create('body_measurement_segments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('body_measurement_id')->constrained('body_measurements')->cascadeOnDelete();
            $table->string('segment_key');
            $table->decimal('lean_mass_kg', 8, 2)->nullable();
            $table->decimal('fat_mass_kg', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['body_measurement_id', 'segment_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('body_measurement_segments');
        Schema::dropIfExists('body_measurements');
    }
};
