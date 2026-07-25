<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRight,
    Flame,
    Gauge,
    HeartPulse,
    Moon,
    Scale,
    UtensilsCrossed,
} from '@lucide/vue';
import type { EChartsCoreOption } from 'echarts/core';
import { computed } from 'vue';
import type { Component } from 'vue';
import BaseChart from '@/components/charts/BaseChart.vue';
import DateNavigator from '@/components/DateNavigator.vue';
import PageSectionCard from '@/components/PageSectionCard.vue';
import PageTabShell from '@/components/PageTabShell.vue';
import { Button } from '@/components/ui/button';
import {
    CHART_COLORS,
    STRENGTH_SERIES_COLORS,
    chartAxisLabel,
    chartAxisLine,
    chartLegend,
    chartLineSeriesStyle,
    chartSplitLine,
    eachDateInclusive,
    nutritionSeriesByDate,
} from '@/lib/chartTheme';
import {
    formatSleepDelta,
    formatSleepMinutes,
    metricLabel,
} from '@/lib/metricLabels';
import { PFC_COLORS } from '@/lib/pfcColors';
import type {
    DailyMetricEntry,
    NutritionChartPoint,
    NutritionGoal,
    NutritionTotals,
} from '@/types/routine';

type ChartPoint = {
    date: string;
    value: string;
};

type StrengthPoint = {
    date: string;
    item_name: string;
    max_load_value: string | null;
};

interface Props {
    date: string;
    chartFrom: string;
    chartTo: string;
    metrics: DailyMetricEntry[];
    previousMetrics: DailyMetricEntry[];
    mealTotals: NutritionTotals;
    mealGoal: NutritionGoal | null;
    mealChartPoints: NutritionChartPoint[];
    conditionChartSeries: Record<string, ChartPoint[]>;
    strengthChartPoints: StrengthPoint[];
}

const props = defineProps<Props>();

const metricIcons: Record<string, Component> = {
    weight: Scale,
    sleep_minutes: Moon,
    pain_level: HeartPulse,
    pitch_speed_max: Gauge,
};

const chartColors = [...STRENGTH_SERIES_COLORS];

function metricValue(list: DailyMetricEntry[], key: string): number | null {
    const entry = list.find((item) => item.metric.key === key);

    if (!entry?.record?.value) {
        return null;
    }

    return Number(entry.record.value);
}

function formatMetric(key: string, value: number | null): string {
    if (value === null || Number.isNaN(value)) {
        return '—';
    }

    if (key === 'sleep_minutes') {
        return formatSleepMinutes(value);
    }

    if (key === 'pain_level' || key === 'fatigue_level') {
        return `${Math.round(value)} / 5`;
    }

    if (key === 'weight' || key === 'pitch_speed_max') {
        return value.toLocaleString('ja-JP', { maximumFractionDigits: 1 });
    }

    return String(Math.round(value));
}

function deltaLabel(
    key: string,
    today: number | null,
    prev: number | null,
): string | null {
    if (today === null || prev === null) {
        return null;
    }

    const diff = today - prev;

    if (Math.abs(diff) < 0.05) {
        return '変化なし（前日比）';
    }

    if (key === 'sleep_minutes') {
        return `${formatSleepDelta(diff)}（前日比）`;
    }

    const sign = diff > 0 ? '▲' : '▼';
    const abs = Math.abs(diff).toLocaleString('ja-JP', {
        maximumFractionDigits: 1,
    });

    return `${sign} ${abs}（前日比）`;
}

const summaryMetrics = computed(() =>
    ['weight', 'sleep_minutes', 'pain_level', 'pitch_speed_max'].map((key) => {
        const today = metricValue(props.metrics, key);
        const prev = metricValue(props.previousMetrics, key);
        const meta = props.metrics.find(
            (item) => item.metric.key === key,
        )?.metric;

        return {
            key,
            label: metricLabel(key, meta?.label),
            unit: meta?.unit ?? '',
            display: formatMetric(key, today),
            delta: deltaLabel(key, today, prev),
            icon: metricIcons[key] ?? Activity,
        };
    }),
);

const pfcEnergy = computed(() => {
    const p = props.mealTotals.protein_g * 4;
    const f = props.mealTotals.fat_g * 9;
    const c = props.mealTotals.carb_g * 4;
    const total = p + f + c;

    if (total <= 0) {
        return { p: 0, f: 0, c: 0 };
    }

    return {
        p: Math.round((p / total) * 100),
        f: Math.round((f / total) * 100),
        c: Math.round((c / total) * 100),
    };
});

const pfcDonutStyle = computed(() => {
    const { p, f, c } = pfcEnergy.value;

    if (p + f + c <= 0) {
        return {
            background: 'conic-gradient(var(--cd-line) 0deg 360deg)',
        };
    }

    const pEnd = p * 3.6;
    const fEnd = pEnd + f * 3.6;

    return {
        background: `conic-gradient(
            ${PFC_COLORS.p.css} 0deg ${pEnd}deg,
            ${PFC_COLORS.f.css} ${pEnd}deg ${fEnd}deg,
            ${PFC_COLORS.c.css} ${fEnd}deg 360deg
        )`,
    };
});

const kcalProgress = computed(() => {
    if (!props.mealGoal) {
        return null;
    }

    const target = Number(props.mealGoal.kcal);

    if (target <= 0) {
        return null;
    }

    return Math.min(100, Math.round((props.mealTotals.kcal / target) * 100));
});

const hasMealChartData = computed(() =>
    props.mealChartPoints.some(
        (point) =>
            point.kcal > 0 ||
            point.protein_g > 0 ||
            point.fat_g > 0 ||
            point.carb_g > 0,
    ),
);

const hasConditionChartData = computed(() =>
    Object.values(props.conditionChartSeries).some(
        (points) => points.length > 0,
    ),
);

const hasStrengthChartData = computed(
    () => props.strengthChartPoints.length > 0,
);

const mealKcalChartOption = computed<EChartsCoreOption>(() => {
    const { dates, values } = nutritionSeriesByDate(
        props.mealChartPoints,
        props.chartFrom,
        props.chartTo,
        'kcal',
    );

    return {
        grid: { left: 40, right: 12, top: 16, bottom: 24 },
        tooltip: { trigger: 'axis' },
        xAxis: {
            type: 'category',
            data: dates.map((date) => date.slice(5)),
            axisLabel: chartAxisLabel(10),
            axisLine: chartAxisLine(),
        },
        yAxis: {
            type: 'value',
            axisLabel: chartAxisLabel(10),
            splitLine: chartSplitLine(),
        },
        series: [
            {
                name: 'kcal',
                type: 'line',
                smooth: true,
                ...chartLineSeriesStyle(CHART_COLORS.primary),
                data: values,
                areaStyle: { color: CHART_COLORS.primaryArea },
            },
        ],
    };
});

const mealPfcChartOption = computed<EChartsCoreOption>(() => {
    const protein = nutritionSeriesByDate(
        props.mealChartPoints,
        props.chartFrom,
        props.chartTo,
        'protein_g',
    );
    const fat = nutritionSeriesByDate(
        props.mealChartPoints,
        props.chartFrom,
        props.chartTo,
        'fat_g',
    );
    const carb = nutritionSeriesByDate(
        props.mealChartPoints,
        props.chartFrom,
        props.chartTo,
        'carb_g',
    );

    return {
        grid: { left: 40, right: 12, top: 28, bottom: 24 },
        tooltip: { trigger: 'axis' },
        legend: chartLegend(10),
        xAxis: {
            type: 'category',
            data: protein.dates.map((date) => date.slice(5)),
            axisLabel: chartAxisLabel(10),
            axisLine: chartAxisLine(),
        },
        yAxis: {
            type: 'value',
            axisLabel: chartAxisLabel(10),
            splitLine: chartSplitLine(),
        },
        series: [
            {
                name: 'P',
                type: 'bar',
                stack: 'pfc',
                barMaxWidth: 28,
                data: protein.values,
                itemStyle: { color: PFC_COLORS.p.hex },
            },
            {
                name: 'F',
                type: 'bar',
                stack: 'pfc',
                barMaxWidth: 28,
                data: fat.values,
                itemStyle: { color: PFC_COLORS.f.hex },
            },
            {
                name: 'C',
                type: 'bar',
                stack: 'pfc',
                barMaxWidth: 28,
                data: carb.values,
                itemStyle: {
                    color: PFC_COLORS.c.hex,
                    borderRadius: [4, 4, 0, 0],
                },
            },
        ],
    };
});

const conditionChartOption = computed<EChartsCoreOption>(() => {
    const dates = Array.from(
        new Set(
            Object.values(props.conditionChartSeries)
                .flat()
                .map((point) => point.date),
        ),
    ).sort();

    const weightMap = new Map(
        (props.conditionChartSeries.weight ?? []).map((point) => [
            point.date,
            Number(point.value),
        ]),
    );
    const sleepHoursMap = new Map(
        (props.conditionChartSeries.sleep_minutes ?? []).map((point) => [
            point.date,
            Math.round((Number(point.value) / 60) * 10) / 10,
        ]),
    );

    return {
        // Mini chart: keep axis names off the plot — legend + tooltip carry units.
        grid: { left: 40, right: 36, top: 12, bottom: 48 },
        tooltip: { trigger: 'axis' },
        legend: chartLegend(10, { bottom: 0, left: 'center' }),
        xAxis: {
            type: 'category',
            data: dates.map((date) => date.slice(5)),
            axisLabel: chartAxisLabel(10),
            axisLine: chartAxisLine(),
        },
        yAxis: [
            {
                type: 'value',
                axisLabel: chartAxisLabel(10),
                splitLine: chartSplitLine(),
                scale: true,
            },
            {
                type: 'value',
                axisLabel: chartAxisLabel(10),
                splitLine: { show: false },
                min: 0,
                max: (extent: { max: number }) =>
                    Math.max(10, Math.ceil((extent.max || 0) + 1)),
            },
        ],
        series: [
            {
                name: '体重',
                type: 'line' as const,
                smooth: true,
                yAxisIndex: 0,
                ...chartLineSeriesStyle(CHART_COLORS.primary),
                data: dates.map((date) => weightMap.get(date) ?? null),
                tooltip: {
                    valueFormatter: (value: unknown) =>
                        value == null || value === ''
                            ? '—'
                            : `${value} kg`,
                },
            },
            {
                name: '睡眠',
                type: 'line' as const,
                smooth: true,
                yAxisIndex: 1,
                ...chartLineSeriesStyle(CHART_COLORS.secondary),
                data: dates.map((date) => sleepHoursMap.get(date) ?? null),
                tooltip: {
                    valueFormatter: (value: unknown) =>
                        value == null || value === ''
                            ? '—'
                            : `${value} 時間`,
                },
            },
        ],
    };
});

const strengthItemNames = computed(() =>
    [...new Set(props.strengthChartPoints.map((point) => point.item_name))]
        .sort()
        .slice(0, 3),
);

const strengthDates = computed(() =>
    eachDateInclusive(props.chartFrom, props.chartTo),
);

const strengthChartOption = computed<EChartsCoreOption>(() => ({
    // Long item names sit in the bottom legend so they do not cover the plot.
    grid: { left: 40, right: 12, top: 12, bottom: 56 },
    legend: chartLegend(10, { bottom: 0, left: 'center' }),
    tooltip: { trigger: 'axis' },
    xAxis: {
        type: 'category',
        data: strengthDates.value.map((date) => date.slice(5)),
        axisLabel: chartAxisLabel(10),
        axisLine: chartAxisLine(),
    },
    yAxis: {
        type: 'value',
        name: 'kg',
        nameGap: 8,
        nameTextStyle: chartAxisLabel(10),
        axisLabel: chartAxisLabel(10),
        splitLine: chartSplitLine(),
    },
    series: strengthItemNames.value.map((itemName, index) => {
        const byDate = new Map(
            props.strengthChartPoints
                .filter((point) => point.item_name === itemName)
                .map((point) => [point.date, point.max_load_value]),
        );
        const color = chartColors[index % chartColors.length];

        return {
            name: itemName,
            type: 'line' as const,
            smooth: true,
            ...chartLineSeriesStyle(color),
            data: strengthDates.value.map((date) => {
                const value = byDate.get(date);

                return value != null ? Number(value) : null;
            }),
        };
    }),
}));
</script>

<template>
    <Head title="パフォーマンス管理" />

    <div class="flex h-full flex-1 flex-col rounded-xl p-4 md:px-6 md:pb-6">
        <div
            class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-4 md:gap-5"
        >
            <PageTabShell
                title="パフォーマンス管理"
                subtitle="食事とコンディションを、すぐ記録して、すぐ振り返る"
            >
                <template #calendar>
                    <DateNavigator
                        compact
                        :date="date"
                        route-url="/records"
                        :reload-only="[
                            'metrics',
                            'previousMetrics',
                            'mealTotals',
                            'mealGoal',
                            'mealChartPoints',
                            'conditionChartSeries',
                            'strengthChartPoints',
                            'chartFrom',
                            'chartTo',
                            'date',
                        ]"
                    />
                </template>
            </PageTabShell>

            <PageSectionCard padding="none" aria-label="本日のサマリ">
                <div
                    class="grid grid-cols-2 divide-x divide-y divide-cd-line md:grid-cols-3 xl:grid-cols-6 xl:divide-y-0"
                >
                    <div class="relative p-4 pr-14">
                        <Flame
                            class="pointer-events-none absolute top-3 right-3 text-cd-icon-primary opacity-90"
                            :size="28"
                            :stroke-width="1.5"
                        />
                        <p class="font-sans text-xs text-cd-ink-muted">
                            摂取カロリー
                        </p>
                        <p
                            class="mt-2 font-sans text-2xl font-semibold text-cd-ink"
                        >
                            {{
                                Number(mealTotals.kcal).toLocaleString(
                                    'ja-JP',
                                    { maximumFractionDigits: 0 },
                                )
                            }}
                            <span class="text-sm font-medium text-cd-ink-muted"
                                >kcal</span
                            >
                        </p>
                        <p
                            v-if="mealGoal"
                            class="mt-1 font-sans text-xs text-cd-ink-muted"
                        >
                            目標
                            {{
                                Number(mealGoal.kcal).toLocaleString('ja-JP', {
                                    maximumFractionDigits: 0,
                                })
                            }}
                            kcal
                        </p>
                        <div
                            v-if="kcalProgress !== null"
                            class="mt-3 h-2 overflow-hidden rounded-full bg-cd-line/40"
                        >
                            <div
                                class="h-full rounded-full bg-primary"
                                :style="{ width: `${kcalProgress}%` }"
                            />
                        </div>
                    </div>

                    <div class="p-4">
                        <p class="font-sans text-xs text-cd-ink-muted">
                            PFC バランス
                        </p>
                        <div class="mt-3 flex items-center gap-3">
                            <div
                                class="relative size-14 shrink-0 rounded-full"
                                :style="pfcDonutStyle"
                            >
                                <div
                                    class="absolute inset-[22%] rounded-full bg-cd-surface"
                                />
                            </div>
                            <div class="flex flex-col gap-1 font-sans text-xs">
                                <span
                                    class="inline-flex items-center gap-1.5 text-cd-pfc-p"
                                >
                                    <span
                                        class="size-2 rounded-sm bg-cd-pfc-p"
                                    />
                                    P {{ pfcEnergy.p }}%
                                </span>
                                <span
                                    class="inline-flex items-center gap-1.5 text-cd-pfc-f"
                                >
                                    <span
                                        class="size-2 rounded-sm bg-cd-pfc-f"
                                    />
                                    F {{ pfcEnergy.f }}%
                                </span>
                                <span
                                    class="inline-flex items-center gap-1.5 text-cd-pfc-c"
                                >
                                    <span
                                        class="size-2 rounded-sm bg-cd-pfc-c"
                                    />
                                    C {{ pfcEnergy.c }}%
                                </span>
                            </div>
                        </div>
                    </div>

                    <div
                        v-for="item in summaryMetrics"
                        :key="item.key"
                        class="relative p-4 pr-14"
                    >
                        <component
                            :is="item.icon"
                            class="pointer-events-none absolute top-3 right-3 text-cd-icon-primary opacity-90"
                            :size="28"
                            :stroke-width="1.5"
                        />
                        <p class="font-sans text-xs text-cd-ink-muted">
                            {{ item.label }}
                        </p>
                        <p
                            class="mt-2 font-sans text-2xl font-semibold text-cd-ink"
                        >
                            {{ item.display }}
                            <span
                                v-if="
                                    item.display !== '—' &&
                                    item.key !== 'sleep_minutes' &&
                                    item.key !== 'pain_level' &&
                                    item.key !== 'fatigue_level'
                                "
                                class="text-sm font-medium text-cd-ink-muted"
                                >{{ item.unit }}</span
                            >
                        </p>
                        <p
                            v-if="item.delta"
                            class="mt-1 font-sans text-xs text-cd-ink-muted"
                        >
                            {{ item.delta }}
                        </p>
                    </div>
                </div>
            </PageSectionCard>

            <div class="grid gap-4 lg:grid-cols-2">
                <PageSectionCard aria-label="食事記録への入り口">
                    <div class="flex flex-col gap-4">
                        <div class="flex items-start gap-3">
                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-cd-icon-bg text-cd-icon-primary"
                            >
                                <UtensilsCrossed
                                    :size="20"
                                    :stroke-width="1.6"
                                />
                            </div>
                            <div>
                                <h2
                                    class="font-sans text-lg font-semibold text-cd-ink"
                                >
                                    食事記録
                                </h2>
                                <p class="font-sans text-sm text-cd-ink-muted">
                                    直近7日の摂取推移を確認して、記録へ進みます。
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="hasMealChartData"
                            class="grid gap-3"
                            aria-label="食事の直近7日推移"
                        >
                            <div>
                                <p
                                    class="mb-1 font-sans text-xs text-cd-ink-muted"
                                >
                                    エネルギー (kcal)
                                </p>
                                <BaseChart
                                    :option="mealKcalChartOption"
                                    class="!h-40"
                                />
                            </div>
                            <div>
                                <p
                                    class="mb-1 font-sans text-xs text-cd-ink-muted"
                                >
                                    PFC (g)
                                </p>
                                <BaseChart
                                    :option="mealPfcChartOption"
                                    class="!h-40"
                                />
                            </div>
                        </div>
                        <p
                            v-else
                            class="rounded-xl border border-dashed border-cd-line px-3 py-6 text-center font-sans text-sm text-cd-ink-muted"
                        >
                            この期間の食事記録がまだありません。記録すると推移グラフが表示されます。
                        </p>

                        <Button as-child class="font-sans tracking-[0.06em]">
                            <Link
                                :href="`/meals?date=${date}`"
                                class="inline-flex items-center gap-2"
                            >
                                食事を記録する
                                <ArrowRight :size="16" :stroke-width="1.6" />
                            </Link>
                        </Button>
                    </div>
                </PageSectionCard>

                <PageSectionCard aria-label="コンディション管理への入り口">
                    <div class="flex flex-col gap-4">
                        <div class="flex items-start gap-3">
                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-cd-icon-bg text-cd-icon-primary"
                            >
                                <Activity :size="20" :stroke-width="1.6" />
                            </div>
                            <div>
                                <h2
                                    class="font-sans text-lg font-semibold text-cd-ink"
                                >
                                    コンディション管理
                                </h2>
                                <p class="font-sans text-sm text-cd-ink-muted">
                                    体重・睡眠の推移と筋力チャートをまとめて確認します。
                                </p>
                            </div>
                        </div>

                        <div aria-label="体重・睡眠の直近7日推移">
                            <p class="mb-1 font-sans text-xs text-cd-ink-muted">
                                体重・睡眠（7日）
                            </p>
                            <BaseChart
                                v-if="hasConditionChartData"
                                :option="conditionChartOption"
                                class="!h-52"
                            />
                            <p
                                v-else
                                class="rounded-xl border border-dashed border-cd-line px-3 py-6 text-center font-sans text-sm text-cd-ink-muted"
                            >
                                まだ推移データがありません。コンディションを記録するとここにグラフが表示されます。
                            </p>
                        </div>

                        <div aria-label="筋力ミニチャート">
                            <div
                                class="mb-1 flex items-center justify-between gap-2"
                            >
                                <p class="font-sans text-xs text-cd-ink-muted">
                                    筋力チャート（7日）
                                </p>
                                <Button
                                    as-child
                                    variant="outline"
                                    size="sm"
                                    class="font-sans"
                                >
                                    <Link
                                        href="/records/strength?period=3months"
                                    >
                                        詳細
                                    </Link>
                                </Button>
                            </div>
                            <BaseChart
                                v-if="hasStrengthChartData"
                                :option="strengthChartOption"
                                class="!h-48"
                            />
                            <p
                                v-else
                                class="rounded-xl border border-dashed border-cd-line px-3 py-6 text-center font-sans text-sm text-cd-ink-muted"
                            >
                                この期間に完了した筋力セッションがありません。
                            </p>
                        </div>

                        <Button as-child class="font-sans tracking-[0.06em]">
                            <Link
                                :href="`/records/condition?date=${date}`"
                                class="inline-flex items-center gap-2"
                            >
                                コンディションを記録する
                                <ArrowRight :size="16" :stroke-width="1.6" />
                            </Link>
                        </Button>
                    </div>
                </PageSectionCard>
            </div>
        </div>
    </div>
</template>
