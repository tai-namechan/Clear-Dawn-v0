<?php

namespace App\Http\Controllers;

use App\Domain\Yoyu\Support\UserTimezoneResolver;
use App\Http\Requests\MetricRecords\ShowDailyRecordsRequest;
use App\Http\Requests\MetricRecords\UpsertDailyMetricsRequest;
use App\Http\Resources\MetricRecordResource;
use App\Http\Resources\MetricResource;
use App\Http\Resources\NutritionGoalResource;
use App\Models\DailyCheckin;
use App\Models\Metric;
use App\Models\MetricRecord;
use App\Queries\GetDailyMealsQuery;
use App\Queries\GetDailyMetricsQuery;
use App\Queries\GetMetricChartQuery;
use App\Queries\GetMetricHistoryQuery;
use App\Queries\GetStrengthChartQuery;
use App\Services\EnsureMetricsService;
use App\Services\UpsertDailyMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MetricRecordController extends Controller
{
    public function index(
        ShowDailyRecordsRequest $request,
        GetDailyMetricsQuery $query,
        GetDailyMealsQuery $mealsQuery,
        EnsureMetricsService $ensureMetrics,
        UserTimezoneResolver $timezoneResolver,
    ): Response {
        $ensureMetrics->handle();

        $user = $request->user();
        $recordedOn = Carbon::parse(
            $request->validated('date') ?? $timezoneResolver->todayDateString($user),
        );
        $previousOn = $recordedOn->copy()->subDay();

        $daily = $query->handle($user, $recordedOn);
        $previous = $query->handle($user, $previousOn);
        $meals = $mealsQuery->handle($user, $recordedOn);

        return Inertia::render('Records/Index', [
            'date' => $recordedOn->toDateString(),
            'metrics' => $this->mapDailyMetrics($daily),
            'previousMetrics' => $this->mapDailyMetrics($previous),
            'mealTotals' => $meals['totals'],
            'mealSections' => array_map(fn (array $section): array => [
                'meal_type' => $section['meal_type'],
                'label' => $section['label'],
                'kcal' => $section['subtotal']['kcal'],
                'entry_count' => count($section['entries']),
            ], $meals['sections']),
            'mealGoal' => $meals['goal'] !== null
                ? NutritionGoalResource::make($meals['goal'])->resolve()
                : null,
        ]);
    }

    public function condition(
        ShowDailyRecordsRequest $request,
        GetDailyMetricsQuery $query,
        GetMetricChartQuery $chartQuery,
        EnsureMetricsService $ensureMetrics,
        UserTimezoneResolver $timezoneResolver,
    ): Response {
        $ensureMetrics->handle();

        $user = $request->user();
        $recordedOn = Carbon::parse(
            $request->validated('date') ?? $timezoneResolver->todayDateString($user),
        );
        $previousOn = $recordedOn->copy()->subDay();
        $from = $recordedOn->copy()->subDays(6);

        $daily = $query->handle($user, $recordedOn);
        $previous = $query->handle($user, $previousOn);

        $chartKeys = ['weight', 'sleep_minutes', 'pitch_speed_max'];
        $metricsByKey = Metric::query()->whereIn('key', $chartKeys)->get()->keyBy('key');
        $chartSeries = [];

        foreach ($chartKeys as $key) {
            /** @var Metric|null $metric */
            $metric = $metricsByKey->get($key);

            if ($metric === null) {
                $chartSeries[$key] = [];

                continue;
            }

            $chartSeries[$key] = $chartQuery
                ->handle($request->user(), $metric, $from, $recordedOn)
                ->values()
                ->all();
        }

        $checkin = DailyCheckin::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('checked_on', $recordedOn->toDateString())
            ->first();

        return Inertia::render('Records/Condition', [
            'date' => $recordedOn->toDateString(),
            'metrics' => $this->mapDailyMetrics($daily),
            'previousMetrics' => $this->mapDailyMetrics($previous),
            'chartSeries' => $chartSeries,
            'checkin' => $checkin === null ? null : [
                'id' => $checkin->id,
                'checked_on' => $checkin->checked_on->toDateString(),
                'sleep_quality' => $checkin->sleep_quality,
                'fatigue' => $checkin->fatigue,
                'muscle_soreness' => $checkin->muscle_soreness,
                'stress' => $checkin->stress,
                'mood' => $checkin->mood,
                'region_tension' => $checkin->region_tension,
                'readiness_self' => $checkin->readiness_self,
                'note' => $checkin->note,
            ],
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

    public function show(
        Request $request,
        Metric $metric,
        GetMetricHistoryQuery $historyQuery,
        GetMetricChartQuery $chartQuery,
        UserTimezoneResolver $timezoneResolver,
    ): Response {
        $today = $timezoneResolver->todayDateString($request->user());
        [$from, $to, $period] = $this->resolveChartRange($request, $today);
        $granularity = $this->resolveGranularity($request);

        $history = $historyQuery->handle($request->user(), $metric, $from, $to);
        $chart = $chartQuery->handle($request->user(), $metric, $from, $to, $granularity);

        return Inertia::render('Records/Show', [
            'metric' => MetricResource::make($metric)->resolve(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'period' => $period,
            'granularity' => $granularity,
            'records' => MetricRecordResource::collection($history)->resolve(),
            'chartPoints' => $chart->values()->all(),
        ]);
    }

    public function strength(
        Request $request,
        GetStrengthChartQuery $chartQuery,
        UserTimezoneResolver $timezoneResolver,
    ): Response {
        $today = $timezoneResolver->todayDateString($request->user());
        [$from, $to, $period] = $this->resolveChartRange($request, $today);

        $chartPoints = $chartQuery->handle($request->user(), $from, $to);

        return Inertia::render('Records/Strength', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'period' => $period,
            'chartPoints' => $chartPoints,
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

    /**
     * @param  array<int, array{metric: Metric, record: MetricRecord|null}>  $daily
     * @return list<array{metric: array<string, mixed>, record: array<string, mixed>|null}>
     */
    private function mapDailyMetrics(array $daily): array
    {
        return array_values(array_map(fn (array $item): array => [
            'metric' => MetricResource::make($item['metric'])->resolve(),
            'record' => $item['record'] !== null
                ? MetricRecordResource::make($item['record'])->resolve()
                : null,
        ], $daily));
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string|null}
     */
    private function resolveChartRange(Request $request, string $today): array
    {
        $to = Carbon::parse($request->input('to', $today))->startOfDay();
        $period = $request->input('period');
        $period = is_string($period) && in_array($period, ['week', 'month', '3months', 'year'], true)
            ? $period
            : null;

        $from = match ($period) {
            'week' => $to->copy()->subDays(6),
            'month' => $to->copy()->subMonthNoOverflow(),
            '3months' => $to->copy()->subMonthsNoOverflow(3),
            'year' => $to->copy()->subYearNoOverflow(),
            default => Carbon::parse($request->input('from', Carbon::parse($today)->subMonths(3)->toDateString()))->startOfDay(),
        };

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy(), $from->copy()];
        }

        return [$from, $to, $period];
    }

    /**
     * @return 'day'|'week'
     */
    private function resolveGranularity(Request $request): string
    {
        $granularity = $request->input('granularity', GetMetricChartQuery::GRANULARITY_DAY);

        if ($granularity === GetMetricChartQuery::GRANULARITY_WEEK) {
            return GetMetricChartQuery::GRANULARITY_WEEK;
        }

        return GetMetricChartQuery::GRANULARITY_DAY;
    }
}
