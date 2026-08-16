<?php

namespace Tests\Feature;

use App\Enums\BodyMeasurementSource;
use App\Enums\BodyMeasurementStatus;
use App\Enums\BodySegment;
use App\Models\BodyMeasurement;
use App\Models\Metric;
use App\Models\MetricRecord;
use App\Models\User;
use Database\Seeders\MatrixRowSeeder;
use Database\Seeders\MetricSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\EvoltSamplePdf;
use Tests\TestCase;

class BodyMeasurementImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MatrixRowSeeder::class);
        $this->seed(MetricSeeder::class);
        Storage::fake('local');
    }

    public function test_guests_cannot_import_body_measurement_pdf(): void
    {
        $this->post(route('records.body-measurements.import'), [
            'date' => '2026-08-16',
            'pdf' => EvoltSamplePdf::uploadedFile(),
        ])->assertRedirect(route('login'));
    }

    public function test_pdf_import_persists_measurement_segments_and_metric_projections(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('records.condition', ['date' => '2026-08-16']))
            ->post(route('records.body-measurements.import'), [
                'date' => '2026-08-16',
                'pdf' => EvoltSamplePdf::uploadedFile(),
            ])
            ->assertRedirect(route('records.condition', ['date' => '2026-08-16']));

        $measurement = BodyMeasurement::query()
            ->where('user_id', $user->id)
            ->whereDate('measured_on', '2026-08-16')
            ->with('segments')
            ->first();

        $this->assertNotNull($measurement);
        $this->assertSame(BodyMeasurementSource::Pdf, $measurement->input_source);
        $this->assertSame(BodyMeasurementStatus::Confirmed, $measurement->parse_status);
        $this->assertSame('92.30', $measurement->weight_kg);
        $this->assertSame('70.30', $measurement->lean_body_mass_kg);
        $this->assertSame('39.00', $measurement->skeletal_muscle_mass_kg);
        $this->assertSame('23.80', $measurement->body_fat_percentage);
        $this->assertSame('95.70', $measurement->abdominal_circumference_cm);
        $this->assertSame('178.0', $measurement->height_cm);
        $this->assertSame(34, $measurement->age);
        $this->assertSame('male', $measurement->gender);
        $this->assertSame('14.80', $measurement->protein_kg);
        $this->assertSame('4.20', $measurement->mineral_kg);
        $this->assertSame('48.60', $measurement->total_body_water_kg);
        $this->assertSame('22.00', $measurement->body_fat_mass_kg);
        $this->assertSame('18.10', $measurement->subcutaneous_fat_mass_kg);
        $this->assertSame('3.90', $measurement->visceral_fat_mass_kg);
        $this->assertSame('112.00', $measurement->visceral_fat_area_cm2);
        $this->assertSame(12, $measurement->visceral_fat_level);
        $this->assertSame(1980, $measurement->bmr_kcal);
        $this->assertSame(2650, $measurement->tee_kcal);
        $this->assertSame(31, $measurement->bio_age);
        $this->assertSame('72.0', $measurement->bwi_score);
        $this->assertSame('0.910', $measurement->waist_to_hip_ratio);
        $this->assertSame(2200, $measurement->recommended_calories_min);
        $this->assertSame(2600, $measurement->recommended_calories_max);
        $this->assertSame('140.0', $measurement->recommended_protein_min_g);
        $this->assertSame('180.0', $measurement->recommended_protein_max_g);
        $this->assertSame('220.0', $measurement->recommended_carbohydrate_min_g);
        $this->assertSame('280.0', $measurement->recommended_carbohydrate_max_g);
        $this->assertSame('55.0', $measurement->recommended_fat_min_g);
        $this->assertSame('75.0', $measurement->recommended_fat_max_g);
        $this->assertNotNull($measurement->measured_at);
        $this->assertSame('2026-08-16 03:07:00', $measurement->measured_at->toDateTimeString());
        $this->assertNotNull($measurement->source_pdf_path);
        $this->assertStringNotContainsString('Taro', json_encode($measurement->raw_extracted_json));
        Storage::disk('local')->assertExists($measurement->source_pdf_path);

        $leftArm = $measurement->segments->firstWhere('segment_key', BodySegment::LeftArm);
        $this->assertNotNull($leftArm);
        $this->assertSame('3.92', $leftArm->lean_mass_kg);
        $this->assertSame('1.36', $leftArm->fat_mass_kg);
        $this->assertCount(5, $measurement->segments);

        $weight = Metric::query()->where('key', 'weight')->firstOrFail();
        $lean = Metric::query()->where('key', 'lean_body_mass')->firstOrFail();

        $this->assertTrue(
            MetricRecord::query()
                ->where('user_id', $user->id)
                ->where('metric_id', $weight->id)
                ->whereDate('recorded_on', '2026-08-16')
                ->where('value', 92.3)
                ->exists(),
        );
        $this->assertTrue(
            MetricRecord::query()
                ->where('user_id', $user->id)
                ->where('metric_id', $lean->id)
                ->whereDate('recorded_on', '2026-08-16')
                ->where('value', 70.3)
                ->exists(),
        );
    }

    public function test_pdf_import_does_not_write_other_users_records(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)
            ->post(route('records.body-measurements.import'), [
                'date' => '2026-08-16',
                'pdf' => EvoltSamplePdf::uploadedFile(),
            ])
            ->assertRedirect();

        $this->assertSame(0, BodyMeasurement::query()->where('user_id', $other->id)->count());
        $this->assertSame(0, MetricRecord::query()->where('user_id', $other->id)->count());
    }

    public function test_unreadable_pdf_is_rejected_without_saving(): void
    {
        $user = User::factory()->create();
        $countBefore = BodyMeasurement::query()->count();

        $this->actingAs($user)
            ->from(route('records.condition', ['date' => '2026-08-16']))
            ->post(route('records.body-measurements.import'), [
                'date' => '2026-08-16',
                'pdf' => EvoltSamplePdf::uploadedFile(['This PDF has no composition numbers']),
            ])
            ->assertRedirect(route('records.condition', ['date' => '2026-08-16']))
            ->assertSessionHasErrors('pdf');

        $this->assertSame($countBefore, BodyMeasurement::query()->count());
    }

    public function test_flate_encoded_evolt_pdf_persists_core_fields_not_water_as_fat(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('records.body-measurements.import'), [
                'date' => '2026-08-16',
                'pdf' => EvoltSamplePdf::uploadedFlateFile(),
            ])
            ->assertRedirect();

        $measurement = BodyMeasurement::query()
            ->where('user_id', $user->id)
            ->whereDate('measured_on', '2026-08-16')
            ->first();

        $this->assertNotNull($measurement);
        $this->assertSame(BodyMeasurementStatus::Confirmed, $measurement->parse_status);
        $this->assertSame('92.30', $measurement->weight_kg);
        $this->assertSame('70.30', $measurement->lean_body_mass_kg);
        $this->assertSame('39.00', $measurement->skeletal_muscle_mass_kg);
        $this->assertSame('23.80', $measurement->body_fat_percentage);
        $this->assertSame('50.60', $measurement->total_body_water_kg);
        $this->assertNotSame('50.60', $measurement->body_fat_percentage);
        $this->assertNotSame('3.00', $measurement->skeletal_muscle_mass_kg);
    }

    public function test_non_pdf_upload_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('records.condition', ['date' => '2026-08-16']))
            ->post(route('records.body-measurements.import'), [
                'date' => '2026-08-16',
                'pdf' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('pdf');
    }

    public function test_needs_review_reimport_clears_previous_weight_and_lean_projections(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $weight = Metric::query()->where('key', 'weight')->firstOrFail();
        $lean = Metric::query()->where('key', 'lean_body_mass')->firstOrFail();
        $sleep = Metric::query()->where('key', 'sleep_minutes')->firstOrFail();

        MetricRecord::factory()->create([
            'user_id' => $user->id,
            'metric_id' => $sleep->id,
            'recorded_on' => '2026-08-16',
            'value' => 420,
        ]);
        MetricRecord::factory()->create([
            'user_id' => $user->id,
            'metric_id' => $weight->id,
            'recorded_on' => '2026-08-15',
            'value' => 91.0,
        ]);
        MetricRecord::factory()->create([
            'user_id' => $other->id,
            'metric_id' => $weight->id,
            'recorded_on' => '2026-08-16',
            'value' => 80.0,
        ]);

        $this->actingAs($user)
            ->post(route('records.body-measurements.import'), [
                'date' => '2026-08-16',
                'pdf' => EvoltSamplePdf::uploadedFile(),
            ])
            ->assertRedirect();

        $this->assertTrue(
            MetricRecord::query()
                ->where('user_id', $user->id)
                ->where('metric_id', $weight->id)
                ->whereDate('recorded_on', '2026-08-16')
                ->where('value', 92.3)
                ->exists(),
        );
        $this->assertTrue(
            MetricRecord::query()
                ->where('user_id', $user->id)
                ->where('metric_id', $lean->id)
                ->whereDate('recorded_on', '2026-08-16')
                ->where('value', 70.3)
                ->exists(),
        );

        $this->actingAs($user)
            ->from(route('records.condition', ['date' => '2026-08-16']))
            ->post(route('records.body-measurements.import'), [
                'date' => '2026-08-16',
                'pdf' => EvoltSamplePdf::uploadedInconsistentFile(),
            ])
            ->assertRedirect(route('records.condition', ['date' => '2026-08-16']));

        $measurement = BodyMeasurement::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(BodyMeasurementStatus::NeedsReview, $measurement->parse_status);
        $this->assertNull($measurement->confirmed_at);
        $this->assertFalse(
            MetricRecord::query()
                ->where('user_id', $user->id)
                ->where('metric_id', $weight->id)
                ->whereDate('recorded_on', '2026-08-16')
                ->exists(),
        );
        $this->assertFalse(
            MetricRecord::query()
                ->where('user_id', $user->id)
                ->where('metric_id', $lean->id)
                ->whereDate('recorded_on', '2026-08-16')
                ->exists(),
        );
        $this->assertTrue(
            MetricRecord::query()
                ->where('user_id', $user->id)
                ->where('metric_id', $sleep->id)
                ->whereDate('recorded_on', '2026-08-16')
                ->where('value', 420)
                ->exists(),
        );
        $this->assertTrue(
            MetricRecord::query()
                ->where('user_id', $user->id)
                ->where('metric_id', $weight->id)
                ->whereDate('recorded_on', '2026-08-15')
                ->where('value', 91.0)
                ->exists(),
        );
        $this->assertTrue(
            MetricRecord::query()
                ->where('user_id', $other->id)
                ->where('metric_id', $weight->id)
                ->whereDate('recorded_on', '2026-08-16')
                ->where('value', 80.0)
                ->exists(),
        );
    }

    public function test_reimport_on_same_day_replaces_the_measurement(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('records.body-measurements.import'), [
                'date' => '2026-08-16',
                'pdf' => EvoltSamplePdf::uploadedFile(),
            ])
            ->assertRedirect();

        $firstPath = BodyMeasurement::query()->where('user_id', $user->id)->value('source_pdf_path');

        $this->actingAs($user)
            ->post(route('records.body-measurements.import'), [
                'date' => '2026-08-16',
                'pdf' => EvoltSamplePdf::uploadedFile([
                    'Weight 90.1 kg',
                    'PBF 22.0 %',
                    'Skeletal Muscle 38.0 kg',
                ]),
            ])
            ->assertRedirect();

        $this->assertSame(1, BodyMeasurement::query()->where('user_id', $user->id)->count());

        $measurement = BodyMeasurement::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('90.10', $measurement->weight_kg);
        $this->assertSame('70.28', $measurement->lean_body_mass_kg);
        $this->assertNull($measurement->bmr_kcal);
        $this->assertNull($measurement->tee_kcal);
        $this->assertNull($measurement->visceral_fat_mass_kg);
        $this->assertNotSame($firstPath, $measurement->source_pdf_path);
        Storage::disk('local')->assertExists($measurement->source_pdf_path);
    }

    public function test_body_story_export_reads_confirmed_measurement_and_does_not_write(): void
    {
        $user = User::factory()->create();
        BodyMeasurement::factory()->for($user)->withSampleSegments()->create([
            'measured_on' => '2026-08-16',
        ]);
        $count = BodyMeasurement::query()->count();

        $this->actingAs($user)
            ->getJson(route('records.story-export', [
                'kind' => 'body',
                'date' => '2026-08-16',
            ]))
            ->assertOk()
            ->assertJsonPath('kind', 'body')
            ->assertJsonPath('template_url', '/images/products/body-story.png')
            ->assertJsonPath('body.weight_kg', 92.3)
            ->assertJsonPath('body.skeletal_muscle_mass_kg', 39)
            ->assertJsonPath('body.body_fat_percentage', 23.8)
            ->assertJsonPath('body.abdominal_circumference_cm', 95.7)
            ->assertJsonPath('body.segments.left_arm.lean_mass_kg', 3.92)
            ->assertJsonPath('body.segments.left_arm.fat_mass_kg', 1.36)
            ->assertJsonPath('body.segments.torso.lean_mass_kg', 29.78);

        $this->assertSame($count, BodyMeasurement::query()->count());
    }

    public function test_body_story_export_excludes_other_users_and_uses_null_not_zero(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        BodyMeasurement::factory()->for($other)->withSampleSegments()->create([
            'measured_on' => '2026-08-16',
        ]);

        $this->actingAs($user)
            ->getJson(route('records.story-export', [
                'kind' => 'body',
                'date' => '2026-08-16',
            ]))
            ->assertOk()
            ->assertJsonPath('body.weight_kg', null)
            ->assertJsonPath('body.lean_body_mass_kg', null)
            ->assertJsonPath('body.skeletal_muscle_mass_kg', null)
            ->assertJsonPath('body.body_fat_percentage', null)
            ->assertJsonPath('body.abdominal_circumference_cm', null)
            ->assertJsonPath('body.segments.left_arm.lean_mass_kg', null)
            ->assertJsonPath('body.segments.left_arm.fat_mass_kg', null);
    }

    public function test_condition_page_includes_todays_body_measurement(): void
    {
        $user = User::factory()->create();
        BodyMeasurement::factory()->for($user)->create([
            'measured_on' => '2026-08-16',
            'weight_kg' => 91.2,
        ]);

        $this->actingAs($user)
            ->get(route('records.condition', ['date' => '2026-08-16']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Records/Condition')
                ->where('bodyMeasurement.weight_kg', 91.2)
                ->where('bodyMeasurement.measured_on', '2026-08-16')
            );
    }
}
