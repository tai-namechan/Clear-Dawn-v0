<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRight,
    Gauge,
    HeartPulse,
    Moon,
    Scale,
    UtensilsCrossed,
} from '@lucide/vue';
import type { EChartsCoreOption } from 'echarts/core';
import { computed, ref } from 'vue';
import type { Component } from 'vue';
import CdProgressRing from '@/components/CdProgressRing.vue';
import BaseChart from '@/components/charts/BaseChart.vue';
import DateNavigator from '@/components/DateNavigator.vue';
import PageSectionCard from '@/components/PageSectionCard.vue';
import PageTabShell from '@/components/PageTabShell.vue';
import BodyStoryExportButton from '@/components/records/BodyStoryExportButton.vue';
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
    kcalAchievement,
    kcalAchievementBadge,
} from '@/lib/kcalAchievement.mjs';
import {
    formatSleepDelta,
    formatSleepMinutes,
    metricLabel,
} from '@/lib/metricLabels';
import { PFC_COLORS } from '@/lib/pfcColors';
import { pfcEnergyShare } from '@/lib/pfcShare.mjs';
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

const pfcEnergy = computed(() => pfcEnergyShare(props.mealTotals));

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

const achievement = computed(() =>
    kcalAchievement(props.mealTotals.kcal, props.mealGoal?.kcal),
);
const achievementBadge = computed(() =>
    kcalAchievementBadge(achievement.value),
);

function formatKcal(value: number | string): string {
    return Number(value).toLocaleString('ja-JP', { maximumFractionDigits: 0 });
}

function formatGrams(value: number | string): string {
    return Number(value).toLocaleString('ja-JP', { maximumFractionDigits: 1 });
}

const pfcRows = computed(() => [
    {
        key: 'p',
        label: 'P',
        grams: props.mealTotals.protein_g,
        percent: pfcEnergy.value.p,
        color: PFC_COLORS.p.css,
    },
    {
        key: 'f',
        label: 'F',
        grams: props.mealTotals.fat_g,
        percent: pfcEnergy.value.f,
        color: PFC_COLORS.f.css,
    },
    {
        key: 'c',
        label: 'C',
        grams: props.mealTotals.carb_g,
        percent: pfcEnergy.value.c,
        color: PFC_COLORS.c.css,
    },
]);

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

/**
 * Shared plot box for meal / condition mini-charts so left and right cards
 * line up in the lg:grid-cols-2 layout. Bottom room is for legends.
 */
const RECORDS_MINI_GRID = {
    left: 40,
    right: 36,
    top: 10,
    bottom: 48,
} as const;

const mealKcalChartOption = computed<EChartsCoreOption>(() => {
    const { dates, values } = nutritionSeriesByDate(
        props.mealChartPoints,
        props.chartFrom,
        props.chartTo,
        'kcal',
    );

    return {
        grid: { ...RECORDS_MINI_GRID },
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
                ...chartLineSeriesStyle(TREND_COLOR),
                data: values,
                areaStyle: { color: 'rgba(107, 90, 237, 0.12)' },
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
        grid: { ...RECORDS_MINI_GRID },
        tooltip: { trigger: 'axis' },
        legend: chartLegend(10, { bottom: 0, left: 'center' }),
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
        grid: { ...RECORDS_MINI_GRID },
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
                        value == null || value === '' ? '—' : `${value} kg`,
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
                        value == null || value === '' ? '—' : `${value} 時間`,
                },
            },
        ],
    };
});

/** Brand violet for the 7-day trend card (mockup). */
const TREND_COLOR = '#6B5AED';

type TrendTab = 'kcal' | 'weight' | 'pfc';

const trendTabs: { id: TrendTab; label: string }[] = [
    { id: 'kcal', label: 'カロリー' },
    { id: 'weight', label: '体重' },
    { id: 'pfc', label: 'PFC' },
];
const trendTab = ref<TrendTab>('kcal');

const hasWeightChartData = computed(
    () => (props.conditionChartSeries.weight ?? []).length > 0,
);

const weightChartOption = computed<EChartsCoreOption>(() => {
    const dates = eachDateInclusive(props.chartFrom, props.chartTo);
    const weightByDate = new Map(
        (props.conditionChartSeries.weight ?? []).map((point) => [
            point.date,
            Number(point.value),
        ]),
    );

    return {
        grid: { ...RECORDS_MINI_GRID, bottom: 28 },
        tooltip: { trigger: 'axis' },
        xAxis: {
            type: 'category',
            data: dates.map((date) => date.slice(5)),
            axisLabel: chartAxisLabel(10),
            axisLine: chartAxisLine(),
        },
        yAxis: {
            type: 'value',
            scale: true,
            axisLabel: chartAxisLabel(10),
            splitLine: chartSplitLine(),
        },
        series: [
            {
                name: '体重',
                type: 'line',
                smooth: true,
                ...chartLineSeriesStyle(TREND_COLOR),
                data: dates.map((date) => weightByDate.get(date) ?? null),
                tooltip: {
                    valueFormatter: (value: unknown) =>
                        value == null || value === '' ? '—' : `${value} kg`,
                },
            },
        ],
    };
});

const trendOption = computed<EChartsCoreOption>(() => {
    if (trendTab.value === 'weight') {
        return weightChartOption.value;
    }

    if (trendTab.value === 'pfc') {
        return mealPfcChartOption.value;
    }

    return mealKcalChartOption.value;
});

const trendHasData = computed(() =>
    trendTab.value === 'weight'
        ? hasWeightChartData.value
        : hasMealChartData.value,
);

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
    // Axis name "kg" is omitted — it collides with tick labels in this height.
    grid: { ...RECORDS_MINI_GRID },
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
            tooltip: {
                valueFormatter: (value: unknown) =>
                    value == null || value === '' ? '—' : `${value} kg`,
            },
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

            <!-- Intake vs goal (mockup: achievement ring) -->
            <section
                class="cd-panel flex items-center gap-5 p-5"
                aria-label="摂取カロリーの達成率"
            >
                <CdProgressRing
                    :percent="achievement.ringPercent"
                    size="lg"
                    :label="
                        achievement.percent === null
                            ? '目標未設定'
                            : `達成率 ${achievement.percent}%`
                    "
                >
                    <span class="cd-progress-ring-value">{{
                        achievement.percent === null
                            ? '—'
                            : `${achievement.percent}%`
                    }}</span>
                    <span class="cd-progress-ring-caption">達成率</span>
                </CdProgressRing>
                <div class="min-w-0">
                    <p class="font-sans text-sm text-cd-ink-muted">
                        摂取カロリー
                    </p>
                    <p
                        class="mt-1 font-sans text-[1.7rem] leading-tight font-bold text-cd-ink tabular-nums"
                    >
                        {{ formatKcal(mealTotals.kcal) }}
                        <span class="text-sm font-medium text-cd-ink-muted">{{
                            mealGoal
                                ? `/ ${formatKcal(mealGoal.kcal)} kcal`
                                : 'kcal'
                        }}</span>
                    </p>
                    <p
                        v-if="achievementBadge"
                        class="cd-achievement-badge mt-2"
                        :class="`cd-achievement-badge--${achievement.status}`"
                    >
                        {{ achievementBadge }}
                    </p>
                    <Link
                        v-else
                        :href="`/meals?date=${date}`"
                        class="mt-2 inline-block font-sans text-xs font-semibold text-[var(--cd-hero-primary)] hover:underline"
                    >
                        目標未設定 · 食事記録で設定する
                    </Link>
                </div>
            </section>

            <!-- Condition tiles -->
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <div
                    v-for="item in summaryMetrics"
                    :key="item.key"
                    class="cd-panel flex flex-col items-center p-3 text-center"
                >
                    <component
                        :is="item.icon"
                        class="text-[var(--cd-hero-primary)]"
                        :size="22"
                        :stroke-width="1.7"
                        aria-hidden="true"
                    />
                    <p class="mt-1 font-sans text-xs text-cd-ink-muted">
                        {{ item.label }}
                    </p>
                    <p
                        class="mt-0.5 font-sans text-xl font-bold text-cd-ink tabular-nums"
                    >
                        {{ item.display }}
                        <span
                            v-if="
                                item.display !== '—' &&
                                item.key !== 'sleep_minutes' &&
                                item.key !== 'pain_level' &&
                                item.key !== 'fatigue_level'
                            "
                            class="text-xs font-medium text-cd-ink-muted"
                            >{{ item.unit }}</span
                        >
                    </p>
                    <p
                        v-if="item.delta"
                        class="mt-0.5 font-sans text-[11px] text-cd-ink-muted"
                    >
                        {{ item.delta }}
                    </p>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <!-- PFC balance -->
                <section class="cd-panel p-5" aria-label="PFCバランス">
                    <h2 class="font-sans text-lg font-bold text-cd-ink">
                        PFCバランス
                    </h2>
                    <div class="mt-4 flex items-center gap-6">
                        <div
                            class="relative size-28 shrink-0 rounded-full"
                            :style="pfcDonutStyle"
                        >
                            <div
                                class="absolute inset-[24%] rounded-full bg-white"
                            />
                        </div>
                        <ul class="flex min-w-0 flex-1 flex-col gap-2.5">
                            <li
                                v-for="row in pfcRows"
                                :key="row.key"
                                class="flex items-center gap-2 font-sans text-sm text-cd-ink"
                            >
                                <span
                                    aria-hidden="true"
                                    class="size-2.5 shrink-0 rounded-full"
                                    :style="{ background: row.color }"
                                />
                                <span class="w-4 font-bold">{{
                                    row.label
                                }}</span>
                                <span class="font-bold tabular-nums"
                                    >{{ formatGrams(row.grams) }} g</span
                                >
                                <span
                                    class="ml-auto text-cd-ink-muted tabular-nums"
                                    >({{ row.percent }}%)</span
                                >
                            </li>
                        </ul>
                    </div>
                </section>

                <!-- 7-day trend -->
                <section class="cd-panel p-5" aria-label="7日間の推移">
                    <h2 class="font-sans text-lg font-bold text-cd-ink">
                        7日間の推移
                    </h2>
                    <div
                        class="cd-segment mt-3"
                        role="tablist"
                        aria-label="推移の表示切替"
                    >
                        <button
                            v-for="tab in trendTabs"
                            :key="tab.id"
                            type="button"
                            role="tab"
                            :aria-selected="trendTab === tab.id"
                            @click="trendTab = tab.id"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                    <BaseChart
                        v-if="trendHasData"
                        :option="trendOption"
                        class="mt-3 !h-44"
                    />
                    <p
                        v-else
                        class="mt-3 flex !h-44 items-center justify-center rounded-xl border border-dashed border-cd-line px-3 text-center font-sans text-sm text-cd-ink-muted"
                    >
                        この期間の記録がまだありません。記録すると推移が表示されます。
                    </p>
                </section>
            </div>

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
                                    今日の食事を記録し、週の記録を画像にできます。
                                </p>
                            </div>
                        </div>

                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:items-center"
                        >
                            <BodyStoryExportButton
                                kind="weekly"
                                :date="date"
                                label="週の記録を画像にする"
                            />
                            <Button
                                as-child
                                class="font-sans tracking-[0.06em]"
                            >
                                <Link
                                    :href="`/meals?date=${date}`"
                                    class="inline-flex items-center gap-2"
                                >
                                    食事を記録する
                                    <ArrowRight
                                        :size="16"
                                        :stroke-width="1.6"
                                    />
                                </Link>
                            </Button>
                        </div>
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

                        <div
                            class="grid gap-3"
                            aria-label="コンディションの直近7日推移"
                        >
                            <div aria-label="体重・睡眠の直近7日推移">
                                <div class="mb-1 flex min-h-7 items-center">
                                    <p
                                        class="font-sans text-xs text-cd-ink-muted"
                                    >
                                        体重・睡眠（7日）
                                    </p>
                                </div>
                                <BaseChart
                                    v-if="hasConditionChartData"
                                    :option="conditionChartOption"
                                    class="!h-40"
                                />
                                <p
                                    v-else
                                    class="flex !h-40 items-center justify-center rounded-xl border border-dashed border-cd-line px-3 text-center font-sans text-sm text-cd-ink-muted"
                                >
                                    まだ推移データがありません。コンディションを記録するとここにグラフが表示されます。
                                </p>
                            </div>

                            <div aria-label="筋力ミニチャート">
                                <div
                                    class="mb-1 flex min-h-7 items-center justify-between gap-2"
                                >
                                    <p
                                        class="font-sans text-xs text-cd-ink-muted"
                                    >
                                        筋力チャート（7日）
                                    </p>
                                    <Button
                                        as-child
                                        variant="outline"
                                        size="sm"
                                        class="h-7 font-sans"
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
                                    class="!h-40"
                                />
                                <p
                                    v-else
                                    class="flex !h-40 items-center justify-center rounded-xl border border-dashed border-cd-line px-3 text-center font-sans text-sm text-cd-ink-muted"
                                >
                                    この期間に完了した筋力セッションがありません。
                                </p>
                            </div>
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
