<?php

namespace App\Http\Controllers;

use App\Http\Requests\BodyMeasurements\ImportBodyMeasurementRequest;
use App\Models\BodyMeasurement;
use App\Models\User;
use App\Services\ImportBodyMeasurementFromPdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * 体組成PDF取り込み。
 */
class BodyMeasurementImportController extends Controller
{
    /**
     * 体組成PDFを解析して当日の測定として保存する。
     */
    public function store(
        ImportBodyMeasurementRequest $request,
        ImportBodyMeasurementFromPdfService $service,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        Gate::authorize('create', BodyMeasurement::class);

        $pdf = $request->file('pdf');
        abort_unless($pdf instanceof UploadedFile, 422);

        $service->handle(
            $user,
            Carbon::parse((string) $request->validated('date')),
            $pdf,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => '体組成PDFを取り込みました。']);

        return redirect()->route('records.condition', [
            'date' => $request->validated('date'),
        ]);
    }
}
