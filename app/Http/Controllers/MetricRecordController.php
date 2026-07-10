<?php

namespace App\Http\Controllers;

use App\Http\Requests\MetricRecords\UpsertDailyMetricsRequest;
use App\Http\Resources\MetricRecordResource;
use App\Http\Resources\MetricResource;
use App\Models\Metric;
use App\Models\MetricRecord;
use App\Queries\GetDailyMealsQuery;
use App\Queries\GetDailyMetricsQuery;
use App\Queries\GetMetricChartQuery;
use App\Queries\GetMetricHistoryQuery;
use App\Services\UpsertDailyMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MetricRecordController extends Controller
{
    public function index(Request $request, GetDailyMetricsQuery $query, GetDailyMealsQuery $mealsQuery): Response
    {
        $recordedOn = Carbon::parse($request->input('date', now()->toDateString()));
        $daily = $query->handle($request->user(), $recordedOn);
        $meals = $mealsQuery->handle($request->user(), $recordedOn);

        return Inertia::render('Records/Index', [
            'date' => $recordedOn->toDateString(),
            'metrics' => array_map(fn (array $item): array => [
                'metric' => MetricResource::make($item['metric'])->resolve(),
                'record' => $item['record'] !== null
                    ? MetricRecordResource::make($item['record'])->resolve()
                    : null,
            ], $daily),
            'mealTotals' => $meals['totals'],
        ]);
    }

    public function upsertDaily(UpsertDailyMetricsRequest $request, UpsertDailyMetricsService $service): JsonResponse
    {
        $validated = $request->validated();

        $service->handle(
            $request->user(),
            Carbon::parse($validated['recorded_on']),
            $validated['records'],
        );

        return response()->json(['saved' => true]);
    }

    public function show(Request $request, Metric $metric, GetMetricHistoryQuery $historyQuery, GetMetricChartQuery $chartQuery): Response
    {
        $from = Carbon::parse($request->input('from', now()->subMonths(3)->toDateString()));
        $to = Carbon::parse($request->input('to', now()->toDateString()));

        $history = $historyQuery->handle($request->user(), $metric, $from, $to);
        $chart = $chartQuery->handle($request->user(), $metric, $from, $to);

        return Inertia::render('Records/Show', [
            'metric' => MetricResource::make($metric)->resolve(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'records' => MetricRecordResource::collection($history)->resolve(),
            'chartPoints' => $chart->values()->all(),
        ]);
    }

    public function destroy(Metric $metric, MetricRecord $metricRecord): JsonResponse
    {
        Gate::authorize('delete', $metricRecord);

        if ($metricRecord->metric_id !== $metric->id) {
            abort(404);
        }

        $metricRecord->delete();

        return response()->json(['deleted' => true]);
    }
}
