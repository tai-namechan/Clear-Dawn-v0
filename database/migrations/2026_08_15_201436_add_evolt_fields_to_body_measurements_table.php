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
        Schema::table('body_measurements', function (Blueprint $table) {
            $table->timestamp('measured_at')->nullable()->after('measured_on');
            $table->decimal('height_cm', 5, 1)->nullable()->after('abdominal_circumference_cm');
            $table->unsignedSmallInteger('age')->nullable()->after('height_cm');
            $table->string('gender', 16)->nullable()->after('age');
            $table->decimal('protein_kg', 8, 2)->nullable()->after('gender');
            $table->decimal('mineral_kg', 8, 2)->nullable()->after('protein_kg');
            $table->decimal('total_body_water_kg', 8, 2)->nullable()->after('mineral_kg');
            $table->decimal('body_fat_mass_kg', 8, 2)->nullable()->after('total_body_water_kg');
            $table->decimal('subcutaneous_fat_mass_kg', 8, 2)->nullable()->after('body_fat_mass_kg');
            $table->decimal('visceral_fat_mass_kg', 8, 2)->nullable()->after('subcutaneous_fat_mass_kg');
            $table->decimal('visceral_fat_area_cm2', 8, 2)->nullable()->after('visceral_fat_mass_kg');
            $table->unsignedSmallInteger('visceral_fat_level')->nullable()->after('visceral_fat_area_cm2');
            $table->unsignedInteger('bmr_kcal')->nullable()->after('visceral_fat_level');
            $table->unsignedInteger('tee_kcal')->nullable()->after('bmr_kcal');
            $table->unsignedSmallInteger('bio_age')->nullable()->after('tee_kcal');
            $table->decimal('bwi_score', 5, 1)->nullable()->after('bio_age');
            $table->decimal('waist_to_hip_ratio', 4, 3)->nullable()->after('bwi_score');
            $table->unsignedInteger('recommended_calories_min')->nullable()->after('waist_to_hip_ratio');
            $table->unsignedInteger('recommended_calories_max')->nullable()->after('recommended_calories_min');
            $table->decimal('recommended_protein_min_g', 6, 1)->nullable()->after('recommended_calories_max');
            $table->decimal('recommended_protein_max_g', 6, 1)->nullable()->after('recommended_protein_min_g');
            $table->decimal('recommended_carbohydrate_min_g', 6, 1)->nullable()->after('recommended_protein_max_g');
            $table->decimal('recommended_carbohydrate_max_g', 6, 1)->nullable()->after('recommended_carbohydrate_min_g');
            $table->decimal('recommended_fat_min_g', 6, 1)->nullable()->after('recommended_carbohydrate_max_g');
            $table->decimal('recommended_fat_max_g', 6, 1)->nullable()->after('recommended_fat_min_g');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('body_measurements', function (Blueprint $table) {
            $table->dropColumn([
                'measured_at',
                'height_cm',
                'age',
                'gender',
                'protein_kg',
                'mineral_kg',
                'total_body_water_kg',
                'body_fat_mass_kg',
                'subcutaneous_fat_mass_kg',
                'visceral_fat_mass_kg',
                'visceral_fat_area_cm2',
                'visceral_fat_level',
                'bmr_kcal',
                'tee_kcal',
                'bio_age',
                'bwi_score',
                'waist_to_hip_ratio',
                'recommended_calories_min',
                'recommended_calories_max',
                'recommended_protein_min_g',
                'recommended_protein_max_g',
                'recommended_carbohydrate_min_g',
                'recommended_carbohydrate_max_g',
                'recommended_fat_min_g',
                'recommended_fat_max_g',
            ]);
        });
    }
};
