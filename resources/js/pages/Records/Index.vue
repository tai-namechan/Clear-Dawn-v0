<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRight,
    Check,
    Circle,
    Flame,
    Gauge,
    HeartPulse,
    Moon,
    Scale,
    UtensilsCrossed,
} from '@lucide/vue';
import { computed, type Component } from 'vue';
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
    NutritionGoal,
    NutritionTotals,
} from '@/types/routine';

type MealSectionSummary = {
    meal_type: string;
    label: string;
    kcal: number;
    entry_count: number;
};

interface Props {
    date: string;
    metrics: DailyMetricEntry[];
    previousMetrics: DailyMetricEntry[];
    mealTotals: NutritionTotals;
    mealSections: MealSectionSummary[];
    mealGoal: NutritionGoal | null;
}

const props = defineProps<Props>();

const metricIcons: Record<string, Component> = {
    weight: Scale,
    sleep_minutes: Moon,
    pain_level: HeartPulse,
    pitch_speed_max: Gauge,
};

function metricValue(
    list: DailyMetricEntry[],
    key: string,
): number | null {
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

function deltaLabel(key: string, today: number | null, prev: number | null): string | null {
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
        const meta = props.metrics.find((item) => item.metric.key === key)?.metric;

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
            background:
                'conic-gradient(var(--cd-line) 0deg 360deg)',
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

const conditionHighlights = computed(() =>
    props.metrics.map((entry) => {
        const today = entry.record ? Number(entry.record.value) : null;
        const prev = metricValue(props.previousMetrics, entry.metric.key);

        return {
            key: entry.metric.key,
            label: metricLabel(entry.metric.key, entry.metric.label),
            display: formatMetric(entry.metric.key, today),
            unit: entry.metric.unit,
            delta: deltaLabel(entry.metric.key, today, prev),
        };
    }),
);
</script>

<template>
    <Head title="パフォーマンス管理" />

    <div class="flex h-full flex-1 flex-col rounded-xl p-4 md:px-6 md:pb-6">
        <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-4 md:gap-5">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(280px,0.8fr)]">
                <PageSectionCard>
                    <PageTitleOrnament
                        title="パフォーマンス管理"
                        subtitle="食事とコンディションを、すぐ記録して、すぐ振り返る"
                        align="left"
                    />
                </PageSectionCard>

                <PageSectionCard padding="sm" class="flex items-center justify-center">
                    <DateNavigator
                        :date="date"
                        route-url="/records"
                        :reload-only="[
                            'metrics',
                            'previousMetrics',
                            'mealTotals',
                            'mealSections',
                            'mealGoal',
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
                            <div
                                class="flex flex-col gap-1 font-sans text-xs"
                            >
                                <span class="inline-flex items-center gap-1.5 text-cd-pfc-p">
                                    <span class="size-2 rounded-sm bg-cd-pfc-p" />
                                    P {{ pfcEnergy.p }}%
                                </span>
                                <span class="inline-flex items-center gap-1.5 text-cd-pfc-f">
                                    <span class="size-2 rounded-sm bg-cd-pfc-f" />
                                    F {{ pfcEnergy.f }}%
                                </span>
                                <span class="inline-flex items-center gap-1.5 text-cd-pfc-c">
                                    <span class="size-2 rounded-sm bg-cd-pfc-c" />
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
                                    その日の食事と PFC を記録します。
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                            <div class="min-w-0 flex-1">
                                <p class="font-sans text-sm text-cd-ink-muted">
                                    今日の摂取カロリー
                                </p>
                                <p
                                    class="mt-1 font-sans text-2xl font-semibold text-cd-ink"
                                >
                                    {{
                                        Number(mealTotals.kcal).toLocaleString(
                                            'ja-JP',
                                            { maximumFractionDigits: 0 },
                                        )
                                    }}
                                    <template v-if="mealGoal">
                                        <span
                                            class="text-base font-medium text-cd-ink-muted"
                                        >
                                            /
                                            {{
                                                Number(
                                                    mealGoal.kcal,
                                                ).toLocaleString('ja-JP', {
                                                    maximumFractionDigits: 0,
                                                })
                                            }}
                                            kcal
                                        </span>
                                    </template>
                                    <template v-else>
                                        <span
                                            class="text-base font-medium text-cd-ink-muted"
                                            >kcal</span
                                        >
                                    </template>
                                </p>
                                <div
                                    v-if="kcalProgress !== null"
                                    class="mt-3 h-2.5 overflow-hidden rounded-full bg-cd-line/40"
                                >
                                    <div
                                        class="h-full rounded-full bg-primary"
                                        :style="{ width: `${kcalProgress}%` }"
                                    />
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center gap-3">
                                <div
                                    class="relative size-20 rounded-full"
                                    :style="pfcDonutStyle"
                                >
                                    <div
                                        class="absolute inset-[24%] rounded-full bg-cd-surface"
                                    />
                                </div>
                                <div
                                    class="space-y-1 font-sans text-xs"
                                >
                                    <p class="inline-flex items-center gap-1.5 text-cd-pfc-p">
                                        <span class="size-2 rounded-sm bg-cd-pfc-p" />
                                        P {{ pfcEnergy.p }}%
                                    </p>
                                    <p class="inline-flex items-center gap-1.5 text-cd-pfc-f">
                                        <span class="size-2 rounded-sm bg-cd-pfc-f" />
                                        F {{ pfcEnergy.f }}%
                                    </p>
                                    <p class="inline-flex items-center gap-1.5 text-cd-pfc-c">
                                        <span class="size-2 rounded-sm bg-cd-pfc-c" />
                                        C {{ pfcEnergy.c }}%
                                    </p>
                                </div>
                            </div>
                        </div>

                        <ul class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <li
                                v-for="section in mealSections"
                                :key="section.meal_type"
                                class="rounded-xl border border-cd-line px-3 py-2.5"
                            >
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <span
                                        class="font-sans text-sm font-medium text-cd-ink"
                                        >{{ section.label }}</span
                                    >
                                    <span
                                        v-if="section.entry_count > 0"
                                        class="inline-flex size-4 items-center justify-center rounded-full bg-cd-pfc-p text-white"
                                        aria-label="記録済み"
                                    >
                                        <Check :size="10" :stroke-width="3" />
                                    </span>
                                    <Circle
                                        v-else
                                        class="text-cd-ink-muted"
                                        :size="14"
                                        :stroke-width="1.6"
                                    />
                                </div>
                                <p
                                    class="mt-1 font-sans text-xs text-cd-ink-muted"
                                >
                                    {{
                                        Number(section.kcal).toLocaleString(
                                            'ja-JP',
                                            { maximumFractionDigits: 0 },
                                        )
                                    }}
                                    kcal
                                </p>
                            </li>
                        </ul>

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
                                    体調・回復・パフォーマンスをまとめて記録します。
                                </p>
                            </div>
                        </div>

                        <ul
                            class="overflow-hidden rounded-xl border border-cd-line sm:grid sm:grid-cols-3 sm:divide-x sm:divide-cd-line"
                        >
                            <li
                                v-for="item in conditionHighlights"
                                :key="item.key"
                                class="border-b border-cd-line px-3 py-2.5 last:border-b-0 sm:border-b-0"
                            >
                                <p class="font-sans text-xs text-cd-ink-muted">
                                    {{ item.label }}
                                </p>
                                <p
                                    class="mt-0.5 font-sans text-sm font-semibold text-cd-ink"
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
                                    class="font-sans text-[11px] text-cd-ink-muted"
                                >
                                    {{ item.delta }}
                                </p>
                            </li>
                        </ul>

                        <div
                            class="flex items-center gap-2 rounded-xl border border-dashed border-cd-line px-3 py-3 text-cd-ink-muted"
                        >
                            <Moon :size="16" :stroke-width="1.6" />
                            <p class="font-sans text-xs">
                                7日間の推移グラフはコンディション画面で確認できます。
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
