<?php

namespace App\Services;

use App\Enums\BodyMeasurementSource;
use App\Enums\BodyMeasurementStatus;
use App\Enums\BodySegment;
use App\Models\BodyMeasurement;
use App\Models\BodyMeasurementSegment;
use App\Models\User;
use App\Services\BodyComposition\EvoltBodyCompositionParser;
use App\Services\BodyComposition\ParsedBodyComposition;
use App\Services\BodyComposition\PdfTextExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * 体組成PDFの取り込みと確定保存。
 *
 * 部位別の正本は body_measurements。体重と徐脂肪体重は既存チャート用に metric_records へ投影する。
 */
class ImportBodyMeasurementFromPdfService
{
    public function __construct(
        private PdfTextExtractor $textExtractor,
        private EvoltBodyCompositionParser $parser,
        private EnsureMetricsService $ensureMetrics,
        private UpsertDailyMetricsService $upsertDailyMetrics,
    ) {}

    /**
     * PDFを解析して当日の体組成を確定保存する。
     */
    public function handle(User $user, Carbon $measuredOn, UploadedFile $pdf): BodyMeasurement
    {
        $this->ensureMetrics->handle();

        $absolutePath = $pdf->getRealPath();

        if ($absolutePath === false) {
            throw ValidationException::withMessages([
                'pdf' => 'PDFを読み取れませんでした。',
            ]);
        }

        $parsed = $this->parser->parse($this->textExtractor->extract($absolutePath));

        if (! $parsed->hasPersistableValues()) {
            throw ValidationException::withMessages([
                'pdf' => '体組成の数値を読み取れませんでした。EVOLT 360 のPDFか確認してください。',
            ]);
        }

        $storedPath = $pdf->store('body-scans/'.$user->id, 'local');

        if ($storedPath === false) {
            throw ValidationException::withMessages([
                'pdf' => 'PDFの保存に失敗しました。',
            ]);
        }

        try {
            return DB::transaction(function () use ($user, $measuredOn, $pdf, $parsed, $storedPath): BodyMeasurement {
                $measurement = $this->persistMeasurement($user, $measuredOn, $pdf, $parsed, $storedPath);
                $this->replaceSegments($measurement, $parsed);
                $this->projectMetricRecords($user, $measuredOn, $parsed);

                return $measurement->load('segments');
            });
        } catch (Throwable $e) {
            Storage::disk('local')->delete($storedPath);

            throw $e;
        }
    }

    private function persistMeasurement(
        User $user,
        Carbon $measuredOn,
        UploadedFile $pdf,
        ParsedBodyComposition $parsed,
        string $storedPath,
    ): BodyMeasurement {
        $existing = BodyMeasurement::query()
            ->whereBelongsTo($user)
            ->whereDate('measured_on', $measuredOn->toDateString())
            ->first();

        // 同日再取込では前回PDFを捨て、最新原本だけを残す
        if ($existing?->source_pdf_path !== null && $existing->source_pdf_path !== $storedPath) {
            Storage::disk('local')->delete($existing->source_pdf_path);
        }

        $attributes = [
            ...$parsed->toPersistenceAttributes(),
            'input_source' => BodyMeasurementSource::Pdf,
            'source_pdf_path' => $storedPath,
            'parse_status' => BodyMeasurementStatus::Confirmed,
            'confirmed_at' => now(),
            'raw_extracted_json' => [
                'source_filename' => $pdf->getClientOriginalName(),
                'extracted' => $parsed->raw,
            ],
        ];

        if ($existing !== null) {
            $existing->fill($attributes);
            $existing->save();

            return $existing;
        }

        return $user->bodyMeasurements()->create([
            'measured_on' => $measuredOn->copy()->startOfDay(),
            ...$attributes,
        ]);
    }

    private function replaceSegments(BodyMeasurement $measurement, ParsedBodyComposition $parsed): void
    {
        $measurement->segments()->delete();

        foreach (BodySegment::cases() as $segment) {
            $values = $parsed->segments[$segment->value] ?? [
                'lean_mass_kg' => null,
                'fat_mass_kg' => null,
            ];

            BodyMeasurementSegment::query()->create([
                'body_measurement_id' => $measurement->id,
                'segment_key' => $segment,
                'lean_mass_kg' => $values['lean_mass_kg'],
                'fat_mass_kg' => $values['fat_mass_kg'],
            ]);
        }
    }

    private function projectMetricRecords(User $user, Carbon $measuredOn, ParsedBodyComposition $parsed): void
    {
        $records = [];

        if ($parsed->weightKg !== null) {
            $records[] = [
                'metric_key' => 'weight',
                'value' => $parsed->weightKg,
            ];
        }

        $lean = $parsed->resolvedLeanBodyMassKg();

        if ($lean !== null) {
            $records[] = [
                'metric_key' => 'lean_body_mass',
                'value' => $lean,
            ];
        }

        if ($records === []) {
            return;
        }

        $this->upsertDailyMetrics->handle($user, $measuredOn, $records);
    }
}
