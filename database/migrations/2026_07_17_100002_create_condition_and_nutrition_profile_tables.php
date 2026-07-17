<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3: コンディション・食事再構成（ADR-0011 生データ / 計算結果）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurement_sources', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'key']);
        });

        Schema::create('daily_checkins', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('checked_on');
            $table->unsignedTinyInteger('sleep_quality')->nullable()->comment('Hooper 0-10');
            $table->unsignedTinyInteger('fatigue')->nullable();
            $table->unsignedTinyInteger('muscle_soreness')->nullable();
            $table->unsignedTinyInteger('stress')->nullable();
            $table->unsignedTinyInteger('mood')->nullable();
            $table->json('region_tension')->nullable()->comment('部位別張り 0-10');
            $table->unsignedTinyInteger('readiness_self')->nullable()->comment('主観 readiness 0-10');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'checked_on']);
        });

        Schema::create('symptom_observations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('observed_on');
            $table->string('body_region');
            $table->string('symptom_kind')->comment('例: neural_ulnar / pain / numbness');
            $table->unsignedTinyInteger('severity')->comment('0-10');
            $table->boolean('is_new')->default(false);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'observed_on', 'symptom_kind']);
        });

        Schema::create('personal_baselines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('resource_key');
            $table->decimal('mean_value', 10, 4)->nullable();
            $table->decimal('stddev_value', 10, 4)->nullable();
            $table->unsignedInteger('sample_count')->default(0);
            $table->date('window_start')->nullable();
            $table->date('window_end')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'resource_key']);
        });

        Schema::create('daily_resource_states', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('state_on');
            $table->string('resource_key');
            $table->decimal('ewma', 10, 4)->nullable();
            $table->decimal('z_load', 10, 4)->nullable();
            $table->decimal('rel_strain', 10, 4)->nullable();
            $table->decimal('readiness', 10, 4)->nullable();
            $table->json('inputs_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'state_on', 'resource_key']);
        });

        Schema::create('nutrition_target_profiles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('program_version_id')->nullable()->constrained('program_versions')->nullOnDelete();
            $table->foreignUlid('program_phase_id')->nullable()->constrained('program_phases')->nullOnDelete();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->decimal('kcal', 8, 2);
            $table->decimal('protein_g', 8, 2);
            $table->decimal('fat_g', 8, 2);
            $table->decimal('carb_g', 8, 2);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'starts_on', 'ends_on']);
        });

        Schema::table('metric_records', function (Blueprint $table) {
            $table->foreignUlid('source_id')->nullable()->after('note')->constrained('measurement_sources')->nullOnDelete();
            $table->boolean('is_estimated')->default(false)->after('source_id');
            $table->decimal('reliability', 3, 2)->nullable()->after('is_estimated');
            $table->foreignUlid('corrected_from_id')->nullable()->after('reliability')->constrained('metric_records')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('metric_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_id');
            $table->dropConstrainedForeignId('corrected_from_id');
            $table->dropColumn(['is_estimated', 'reliability']);
        });

        Schema::dropIfExists('nutrition_target_profiles');
        Schema::dropIfExists('daily_resource_states');
        Schema::dropIfExists('personal_baselines');
        Schema::dropIfExists('symptom_observations');
        Schema::dropIfExists('daily_checkins');
        Schema::dropIfExists('measurement_sources');
    }
};
