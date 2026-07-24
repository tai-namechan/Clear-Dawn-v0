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
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import { Button } from '@/components/ui/button';
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

const chartColors = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
];

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

const mealKcalChartOption = computed<EChartsCoreOption>(() => ({
    grid: { left: 40, right: 12, top: 16, bottom: 24 },
    tooltip: { trigger: 'axis' },
    xAxis: {
        type: 'category',
        data: props.mealChartPoints.map((point) => point.date.slice(5)),
        axisLabel: { color: '#5c5a6e', fontSize: 10 },
        axisLine: { lineStyle: { color: '#cfc8d8' } },
    },
    yAxis: {
        type: 'value',
        axisLabel: { color: '#5c5a6e', fontSize: 10 },
        splitLine: {
            lineStyle: { color: '#cfc8d8', opacity: 0.45 },
        },
    },
    series: [
        {
            name: 'kcal',
            type: 'line',
            smooth: true,
            symbol: 'circle',
            symbolSize: 6,
            data: props.mealChartPoints.map((point) => point.kcal),
            lineStyle: { color: '#5b5577', width: 2 },
            itemStyle: { color: '#5b5577' },
            areaStyle: { color: 'rgba(91, 85, 119, 0.12)' },
        },
    ],
}));

const mealPfcChartOption = computed<EChartsCoreOption>(() => ({
    grid: { left: 40, right: 12, top: 28, bottom: 24 },
    tooltip: { trigger: 'axis' },
    legend: {
        top: 0,
        textStyle: { color: '#5c5a6e', fontSize: 10 },
    },
    xAxis: {
        type: 'category',
        data: props.mealChartPoints.map((point) => point.date.slice(5)),
        axisLabel: { color: '#5c5a6e', fontSize: 10 },
        axisLine: { lineStyle: { color: '#cfc8d8' } },
    },
    yAxis: {
        type: 'value',
        axisLabel: { color: '#5c5a6e', fontSize: 10 },
        splitLine: {
            lineStyle: { color: '#cfc8d8', opacity: 0.45 },
        },
    },
    series: [
        {
            name: 'P',
            type: 'bar',
            stack: 'pfc',
            barMaxWidth: 28,
            data: props.mealChartPoints.map((point) => point.protein_g),
            itemStyle: { color: PFC_COLORS.p.hex },
        },
        {
            name: 'F',
            type: 'bar',
            stack: 'pfc',
            barMaxWidth: 28,
            data: props.mealChartPoints.map((point) => point.fat_g),
            itemStyle: { color: PFC_COLORS.f.hex },
        },
        {
            name: 'C',
            type: 'bar',
            stack: 'pfc',
            barMaxWidth: 28,
            data: props.mealChartPoints.map((point) => point.carb_g),
            itemStyle: {
                color: PFC_COLORS.c.hex,
                borderRadius: [4, 4, 0, 0],
            },
        },
    ],
}));

const conditionChartOption = computed<EChartsCoreOption>(() => {
    const dates = Array.from(
        new Set(
            Object.values(props.conditionChartSeries)
                .flat()
                .map((point) => point.date),
        ),
    ).sort();

    const seriesFor = (key: string, color: string, name: string) => {
        const map = new Map(
            (props.conditionChartSeries[key] ?? []).map((point) => [
                point.date,
                Number(point.value),
            ]),
        );

        return {
            name,
            type: 'line' as const,
            smooth: true,
            symbol: 'circle',
            symbolSize: 5,
            data: dates.map((date) => map.get(date) ?? null),
            lineStyle: { color, width: 2 },
            itemStyle: { color },
        };
    };

    return {
        grid: { left: 40, right: 40, top: 28, bottom: 24 },
        tooltip: { trigger: 'axis' },
        legend: {
            top: 0,
            textStyle: { color: '#5c5a6e', fontSize: 10 },
        },
        xAxis: {
            type: 'category',
            data: dates.map((date) => date.slice(5)),
            axisLabel: { color: '#5c5a6e', fontSize: 10 },
            axisLine: { lineStyle: { color: '#cfc8d8' } },
        },
        yAxis: [
            {
                type: 'value',
                name: 'kg',
                axisLabel: { color: '#5c5a6e', fontSize: 10 },
                splitLine: {
                    lineStyle: { color: '#cfc8d8', opacity: 0.45 },
                },
            },
            {
                type: 'value',
                name: '分',
                axisLabel: { color: '#5c5a6e', fontSize: 10 },
                splitLine: { show: false },
            },
        ],
        series: [
            {
                ...seriesFor('weight', '#5b5577', '体重'),
                yAxisIndex: 0,
            },
            {
                ...seriesFor('sleep_minutes', '#2b8fef', '睡眠'),
                yAxisIndex: 1,
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
    [...new Set(props.strengthChartPoints.map((point) => point.date))].sort(),
);

const strengthChartOption = computed<EChartsCoreOption>(() => ({
    grid: { left: 40, right: 12, top: 28, bottom: 24 },
    legend: {
        top: 0,
        textStyle: { color: 'var(--cd-ink-muted)', fontSize: 10 },
    },
    tooltip: { trigger: 'axis' },
    xAxis: {
        type: 'category',
        data: strengthDates.value.map((date) => date.slice(5)),
        axisLabel: {
            color: 'var(--cd-ink-muted)',
            fontSize: 10,
        },
        axisLine: {
            lineStyle: { color: 'var(--cd-line)' },
        },
    },
    yAxis: {
        type: 'value',
        name: 'kg',
        axisLabel: {
            color: 'var(--cd-ink-muted)',
            fontSize: 10,
        },
        splitLine: {
            lineStyle: { color: 'var(--cd-line)', opacity: 0.4 },
        },
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
            symbol: 'circle',
            symbolSize: 5,
            connectNulls: false,
            data: strengthDates.value.map((date) => {
                const value = byDate.get(date);

                return value != null ? Number(value) : null;
            }),
            lineStyle: {
                color,
                width: 2,
            },
            itemStyle: {
                color,
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
            <div
                class="grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(280px,0.8fr)]"
            >
                <PageSectionCard>
                    <PageTitleOrnament
                        title="パフォーマンス管理"
                        subtitle="食事とコンディションを、すぐ記録して、すぐ振り返る"
                        align="left"
                    />
                </PageSectionCard>

                <PageSectionCard
                    padding="sm"
                    class="flex items-center justify-center"
                >
                    <DateNavigator
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
                </PageSectionCard>
            </div>

            <PageSectionCard padding="none" aria-label="本日のサマリ">
                <div
                    class="grid divide-y divide-cd-line sm:grid-cols-2 sm:divide-x md:grid-cols-3 xl:grid-cols-6 xl:divide-y-0"
                >
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-2">
                            <p class="font-sans text-xs text-cd-ink-muted">
                                摂取カロリー
                            </p>
                            <Flame
                                class="text-primary"
                                :size="16"
                                :stroke-width="1.6"
                            />
                        </div>
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
                        class="p-4"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <p class="font-sans text-xs text-cd-ink-muted">
                                {{ item.label }}
                            </p>
                            <component
                                :is="item.icon"
                                class="text-primary"
                                :size="16"
                                :stroke-width="1.6"
                            />
                        </div>
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
                                class="flex size-10 items-center justify-center rounded-full bg-primary/10 text-primary"
                            >
                                <UtensilsCrossed
                                    :size="18"
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
                            <Link :href="`/meals?date=${date}`">
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
                                class="flex size-10 items-center justify-center rounded-full bg-primary/10 text-primary"
                            >
                                <Activity :size="18" :stroke-width="1.6" />
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
                                class="!h-44"
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
                                class="!h-40"
                            />
                            <p
                                v-else
                                class="rounded-xl border border-dashed border-cd-line px-3 py-6 text-center font-sans text-sm text-cd-ink-muted"
                            >
                                この期間に完了した筋力セッションがありません。
                            </p>
                        </div>

                        <Button as-child class="font-sans tracking-[0.06em]">
                            <Link :href="`/records/condition?date=${date}`">
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
