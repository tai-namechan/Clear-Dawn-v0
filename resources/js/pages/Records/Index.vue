<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRight,
    BatteryMedium,
    BedDouble,
    ChevronRight,
    Gauge,
    PencilLine,
    Scale,
    UtensilsCrossed,
    Zap,
} from '@lucide/vue';
import type { EChartsCoreOption } from 'echarts/core';
import { computed } from 'vue';
import type { Component } from 'vue';
import BaseChart from '@/components/charts/BaseChart.vue';
import DateNavigator from '@/components/DateNavigator.vue';
import PageSectionCard from '@/components/PageSectionCard.vue';
import PageTabShell from '@/components/PageTabShell.vue';
import BodyStoryExportButton from '@/components/records/BodyStoryExportButton.vue';
import { Button } from '@/components/ui/button';
import {
    STRENGTH_SERIES_COLORS,
    chartAxisLabel,
    chartAxisLine,
    chartLegend,
    chartLineSeriesStyle,
    chartSplitLine,
    eachDateInclusive,
} from '@/lib/chartTheme';
import { trendAverage, trendAxisLabels } from '@/lib/conditionTrend.mjs';
import { formatSleepDelta, formatSleepMinutes } from '@/lib/metricLabels';
import { show as showMetric } from '@/routes/records';
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

/** Mockup: sleep / weight / fatigue / pain cards, then pitch speed. */
const CONDITION_CARDS: {
    key: string;
    label: string;
    icon: Component;
    tone?: 'gold' | 'violet';
}[] = [
    { key: 'sleep_minutes', label: '睡眠', icon: BedDouble },
    { key: 'weight', label: '体重', icon: Scale },
    {
        key: 'fatigue_level',
        label: '疲労度',
        icon: BatteryMedium,
        tone: 'gold',
    },
    { key: 'pain_level', label: '痛み・しびれ', icon: Zap, tone: 'violet' },
    { key: 'pitch_speed_max', label: '最高球速', icon: Gauge },
];

const LEVEL_KEYS = ['fatigue_level', 'pain_level'];

/** "▼ 0.2（前日比）" → "前日比 ▼0.2" so it fits on a half-width card. */
function compactDelta(label: string | null): string | null {
    if (label === null) {
        return null;
    }

    const body = label.replace('（前日比）', '').replace(' ', '').trim();

    return body === '変化なし' ? '前日比 変化なし' : `前日比 ${body}`;
}
const UNITLESS_KEYS = ['sleep_minutes', ...LEVEL_KEYS];

const conditionCards = computed(() =>
    CONDITION_CARDS.map((card) => {
        const entry = props.metrics.find(
            (item) => item.metric.key === card.key,
        );
        const today = metricValue(props.metrics, card.key);
        const previous = metricValue(props.previousMetrics, card.key);
        const isLevel = LEVEL_KEYS.includes(card.key);

        return {
            ...card,
            display: formatMetric(card.key, today),
            unit:
                today !== null && !UNITLESS_KEYS.includes(card.key)
                    ? (entry?.metric.unit ?? '')
                    : '',
            level:
                isLevel && today !== null
                    ? Math.max(0, Math.min(100, (today / 5) * 100))
                    : null,
            delta:
                card.key === 'weight'
                    ? compactDelta(deltaLabel(card.key, today, previous))
                    : null,
            href: entry ? showMetric(entry.metric.key).url : null,
        };
    }),
);

/**
 * Shared plot box for the compact charts (trend cards and strength chart)
 * so they line up; bottom room is for the date labels / legend.
 */
const RECORDS_MINI_GRID = {
    left: 40,
    right: 16,
    top: 10,
    bottom: 48,
} as const;

type TrendDefinition = {
    key: string;
    label: string;
    icon: Component;
    color: string;
    unit: string;
    range?: [number, number];
    /** Extra room above/below the data so small changes read calmly. */
    padding?: number;
    toChartValue: (value: number) => number;
    formatAverage: (value: number) => string;
};

/** Mockup: sleep (violet), weight (gold) and fatigue (violet) for 7 days. */
const TRENDS: TrendDefinition[] = [
    {
        key: 'sleep_minutes',
        label: '睡眠時間',
        icon: BedDouble,
        color: '#6B5AED',
        unit: '時間',
        padding: 1,
        toChartValue: (minutes) => Math.round((minutes / 60) * 10) / 10,
        formatAverage: (minutes) => formatSleepMinutes(minutes),
    },
    {
        key: 'weight',
        label: '体重',
        icon: Scale,
        color: '#B8893A',
        unit: 'kg',
        padding: 1,
        toChartValue: (value) => value,
        formatAverage: (value) =>
            `${value.toLocaleString('ja-JP', { maximumFractionDigits: 1 })} kg`,
    },
    {
        key: 'fatigue_level',
        label: '疲労度',
        icon: Zap,
        color: '#6B5AED',
        unit: '/ 5',
        range: [1, 5],
        toChartValue: (value) => value,
        formatAverage: (value) =>
            `${value.toLocaleString('ja-JP', { maximumFractionDigits: 1 })} / 5`,
    },
];

const trendDates = computed(() =>
    eachDateInclusive(props.chartFrom, props.chartTo),
);

function trendOption(
    trend: TrendDefinition,
    points: ChartPoint[],
): EChartsCoreOption {
    const valueByDate = new Map(
        points.map((point) => [
            point.date,
            trend.toChartValue(Number(point.value)),
        ]),
    );

    return {
        grid: { ...RECORDS_MINI_GRID, bottom: 24 },
        tooltip: { trigger: 'axis' },
        xAxis: {
            type: 'category',
            data: trendAxisLabels(trendDates.value),
            axisLabel: chartAxisLabel(10),
            axisLine: chartAxisLine(),
        },
        yAxis: {
            type: 'value',
            scale: !trend.range,
            splitNumber: 3,
            min:
                trend.range?.[0] ??
                ((extent: { min: number }) =>
                    Math.floor(extent.min - (trend.padding ?? 0))),
            max:
                trend.range?.[1] ??
                ((extent: { max: number }) =>
                    Math.ceil(extent.max + (trend.padding ?? 0))),
            axisLabel: chartAxisLabel(10),
            splitLine: chartSplitLine(),
        },
        series: [
            {
                name: trend.label,
                type: 'line',
                smooth: 0.3,
                ...chartLineSeriesStyle(trend.color),
                symbol: 'circle',
                symbolSize: 6,
                areaStyle: { color: `${trend.color}1f` },
                data: trendDates.value.map(
                    (date) => valueByDate.get(date) ?? null,
                ),
                tooltip: {
                    valueFormatter: (value: unknown) =>
                        value == null || value === ''
                            ? '—'
                            : `${value} ${trend.unit}`,
                },
            },
        ],
    };
}

const trendCards = computed(() =>
    TRENDS.map((trend) => {
        const points = props.conditionChartSeries[trend.key] ?? [];
        const average = trendAverage(points);

        return {
            ...trend,
            hasData: points.length > 0,
            average: average === null ? null : trend.formatAverage(average),
            option: trendOption(trend, points),
        };
    }),
);

const hasStrengthChartData = computed(
    () => props.strengthChartPoints.length > 0,
);

function formatKcal(value: number | string): string {
    return Number(value).toLocaleString('ja-JP', { maximumFractionDigits: 0 });
}

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
                subtitle="体の状態を、すぐ記録して、すぐ振り返る"
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

            <!-- Today's condition (mockup: 2×2 cards on the sky) -->
            <section
                class="grid grid-cols-2 gap-3 lg:grid-cols-5"
                aria-label="今日のコンディション"
                data-sky-extend
            >
                <component
                    :is="card.href ? Link : 'div'"
                    v-for="card in conditionCards"
                    :key="card.key"
                    :href="card.href ?? undefined"
                    class="cd-panel cd-condition-card"
                    :class="{
                        'col-span-2 lg:col-span-1':
                            card.key === 'pitch_speed_max',
                    }"
                >
                    <span class="cd-condition-icon" aria-hidden="true">
                        <component
                            :is="card.icon"
                            :size="20"
                            :stroke-width="1.7"
                        />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span
                            class="block font-sans text-xs font-bold text-cd-ink"
                            >{{ card.label }}</span
                        >
                        <span
                            class="mt-0.5 block font-sans text-[0.98rem] font-bold whitespace-nowrap text-cd-ink tabular-nums sm:text-lg"
                        >
                            {{ card.display }}
                            <small
                                v-if="card.unit"
                                class="text-xs font-semibold text-cd-ink-muted"
                                >{{ card.unit }}</small
                            >
                        </span>
                        <span
                            v-if="card.delta"
                            class="block font-sans text-[10px] whitespace-nowrap text-cd-ink-muted"
                            >{{ card.delta }}</span
                        >
                        <span
                            v-if="card.level !== null"
                            class="cd-condition-level"
                            aria-hidden="true"
                        >
                            <span
                                :class="`cd-condition-level-fill cd-condition-level-fill--${card.tone}`"
                                :style="{ width: `${card.level}%` }"
                            />
                        </span>
                    </span>
                    <ChevronRight
                        v-if="card.href"
                        :size="15"
                        :stroke-width="1.8"
                        aria-hidden="true"
                        class="absolute top-3 right-2.5 text-cd-ink-muted/70"
                    />
                </component>
            </section>

            <Button
                as-child
                class="h-12 rounded-full font-sans text-base font-bold"
            >
                <Link
                    :href="`/records/condition?date=${date}`"
                    class="inline-flex items-center justify-center gap-2"
                >
                    <PencilLine :size="18" :stroke-width="1.8" />
                    今日のコンディションを記録
                </Link>
            </Button>

            <!-- 7-day trends -->
            <section aria-label="最近の推移" class="flex flex-col gap-3">
                <h2 class="px-1 font-sans text-lg font-bold text-cd-ink">
                    最近の推移
                    <span class="text-sm font-medium text-cd-ink-muted"
                        >（7日間）</span
                    >
                </h2>
                <div class="grid gap-3 lg:grid-cols-3">
                    <article
                        v-for="trend in trendCards"
                        :key="trend.key"
                        class="cd-panel p-4"
                        :aria-label="`${trend.label}の推移`"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <p
                                class="inline-flex items-center gap-2 font-sans text-sm font-bold text-cd-ink"
                            >
                                <component
                                    :is="trend.icon"
                                    :size="18"
                                    :stroke-width="1.7"
                                    aria-hidden="true"
                                    :style="{ color: trend.color }"
                                />
                                {{ trend.label }}
                            </p>
                            <p
                                v-if="trend.average"
                                class="font-sans text-xs text-cd-ink-muted"
                            >
                                平均
                                <strong
                                    class="text-sm font-bold text-cd-ink tabular-nums"
                                    >{{ trend.average }}</strong
                                >
                            </p>
                        </div>
                        <BaseChart
                            v-if="trend.hasData"
                            :option="trend.option"
                            class="mt-2 !h-32"
                        />
                        <p
                            v-else
                            class="mt-2 flex !h-32 items-center justify-center rounded-xl border border-dashed border-cd-line px-3 text-center font-sans text-sm text-cd-ink-muted"
                        >
                            まだ記録がありません
                        </p>
                    </article>
                </div>
            </section>

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
                            <div class="min-w-0">
                                <h2
                                    class="font-sans text-lg font-semibold text-cd-ink"
                                >
                                    食事記録
                                </h2>
                                <p
                                    class="font-sans text-sm text-cd-ink-muted tabular-nums"
                                >
                                    今日
                                    {{ formatKcal(mealTotals.kcal) }}
                                    {{
                                        mealGoal
                                            ? `/ ${formatKcal(mealGoal.kcal)} kcal`
                                            : 'kcal'
                                    }}
                                    · PFC は食事画面で確認できます
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
                    <div class="flex flex-col gap-3">
                        <div class="flex items-start justify-between gap-3">
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
                                        筋力チャート
                                    </h2>
                                    <p
                                        class="font-sans text-sm text-cd-ink-muted"
                                    >
                                        直近7日に完了した筋力セッション
                                    </p>
                                </div>
                            </div>
                            <Button
                                as-child
                                variant="outline"
                                size="sm"
                                class="h-8 shrink-0 font-sans"
                            >
                                <Link href="/records/strength?period=3months">
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
                </PageSectionCard>
            </div>
        </div>
    </div>
</template>
