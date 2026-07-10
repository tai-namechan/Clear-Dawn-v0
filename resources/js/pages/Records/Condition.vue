<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Activity,
    ArrowLeft,
    ArrowRight,
    Gauge,
    HeartPulse,
    Minus,
    Moon,
    Plus,
    Scale,
    Sparkles,
} from '@lucide/vue';
import type { EChartsCoreOption } from 'echarts/core';
import { computed, ref, watch } from 'vue';
import type { Component } from 'vue';
import BaseChart from '@/components/charts/BaseChart.vue';
import DateNavigator from '@/components/DateNavigator.vue';
import PageSectionCard from '@/components/PageSectionCard.vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { apiFetch } from '@/lib/apiFetch';
import {
    formatSleepDelta,
    formatSleepMinutes,
    metricLabel,
} from '@/lib/metricLabels';
import type { ChartPoint, DailyMetricEntry } from '@/types/routine';

interface Props {
    date: string;
    metrics: DailyMetricEntry[];
    previousMetrics: DailyMetricEntry[];
    chartSeries: Record<string, ChartPoint[]>;
}

const props = defineProps<Props>();

type DeltaTone = 'good' | 'bad' | 'neutral';

interface DeltaInfo {
    text: string;
    tone: DeltaTone;
}

const metricIcons: Record<string, Component> = {
    weight: Scale,
    sleep_minutes: Moon,
    pitch_speed_max: Gauge,
    pitch_count: Activity,
    pain_level: HeartPulse,
    fatigue_level: HeartPulse,
};

/**
 * 各指標の「良い方向」。
 * true = 高いほど良い / false = 低いほど良い / null = 中立（体重・投球数など）。
 * 前日比の色分け（改善=緑・悪化=赤）にのみ使う表示上の判定。
 */
const higherIsBetter: Record<string, boolean | null> = {
    weight: null,
    sleep_minutes: true,
    pitch_speed_max: true,
    pitch_count: null,
    pain_level: false,
    fatigue_level: false,
};

/** Always keep form values as strings to avoid Vue number-input trim bugs. */
const values = ref<Record<string, string>>(
    Object.fromEntries(
        props.metrics.map((entry) => [
            entry.metric.key,
            entry.record?.value != null ? String(entry.record.value) : '',
        ]),
    ),
);

const notes = ref<Record<string, string>>(
    Object.fromEntries(
        props.metrics.map((entry) => [
            entry.metric.key,
            entry.record?.note ?? '',
        ]),
    ),
);

const reflection = ref('');
const saving = ref(false);
const saveMessage = ref<string | null>(null);

watch(
    () => props.metrics,
    (metrics) => {
        values.value = Object.fromEntries(
            metrics.map((entry) => [
                entry.metric.key,
                entry.record?.value != null ? String(entry.record.value) : '',
            ]),
        );
        notes.value = Object.fromEntries(
            metrics.map((entry) => [
                entry.metric.key,
                entry.record?.note ?? '',
            ]),
        );
    },
);

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

function formatDisplay(key: string, value: number | null): string {
    if (value === null || Number.isNaN(value)) {
        return '—';
    }

    if (key === 'sleep_minutes') {
        return formatSleepMinutes(value);
    }

    if (key === 'pain_level' || key === 'fatigue_level') {
        return `${Math.round(value)} / 5`;
    }

    return value.toLocaleString('ja-JP', { maximumFractionDigits: 1 });
}

function toneForDelta(key: string, diff: number): DeltaTone {
    const direction = higherIsBetter[key];

    if (direction === null || direction === undefined) {
        return 'neutral';
    }

    const improved = direction ? diff > 0 : diff < 0;

    return improved ? 'good' : 'bad';
}

function deltaInfo(key: string): DeltaInfo | null {
    const today = metricValue(props.metrics, key);
    const prev = metricValue(props.previousMetrics, key);

    if (today === null || prev === null) {
        return null;
    }

    const diff = today - prev;

    if (Math.abs(diff) < 0.05) {
        return { text: '変化なし（前日比）', tone: 'neutral' };
    }

    if (key === 'sleep_minutes') {
        return {
            text: `${formatSleepDelta(diff)}（前日比）`,
            tone: toneForDelta(key, diff),
        };
    }

    const sign = diff > 0 ? '▲' : '▼';
    const abs = Math.abs(diff).toLocaleString('ja-JP', {
        maximumFractionDigits: 1,
    });

    return {
        text: `${sign} ${abs}（前日比）`,
        tone: toneForDelta(key, diff),
    };
}

function toneClass(tone: DeltaTone): string {
    if (tone === 'good') {
        return 'text-cd-moss';
    }

    if (tone === 'bad') {
        return 'text-cd-danger';
    }

    return 'text-cd-ink-muted';
}

const clamp = (value: number, min: number, max: number): number =>
    Math.min(max, Math.max(min, value));

/**
 * 総合コンディション（0-100）。
 * 回復・体調のシグナル（痛み・疲労・睡眠）を 0-100 に正規化して平均する。
 * 痛み/疲労は低いほど良い（1→100, 5→0）、睡眠は 8 時間=100 を上限とする。
 * 入力が 1 つも無ければ null（未算出）。
 */
function conditionScore(list: DailyMetricEntry[]): number | null {
    const pain = metricValue(list, 'pain_level');
    const fatigue = metricValue(list, 'fatigue_level');
    const sleep = metricValue(list, 'sleep_minutes');

    const parts: number[] = [];

    if (pain !== null) {
        parts.push(((5 - clamp(pain, 1, 5)) / 4) * 100);
    }

    if (fatigue !== null) {
        parts.push(((5 - clamp(fatigue, 1, 5)) / 4) * 100);
    }

    if (sleep !== null) {
        parts.push(clamp(sleep / 480, 0, 1) * 100);
    }

    if (parts.length === 0) {
        return null;
    }

    return Math.round(parts.reduce((sum, part) => sum + part, 0) / parts.length);
}

const overall = computed(() => {
    const today = conditionScore(props.metrics);
    const prev = conditionScore(props.previousMetrics);

    let delta: DeltaInfo | null = null;

    if (today !== null && prev !== null) {
        const diff = today - prev;

        if (Math.abs(diff) < 1) {
            delta = { text: '変化なし（前日比）', tone: 'neutral' };
        } else {
            const sign = diff > 0 ? '▲' : '▼';
            delta = {
                text: `${sign} ${Math.abs(diff)}（前日比）`,
                tone: diff > 0 ? 'good' : 'bad',
            };
        }
    }

    return {
        score: today,
        display: today === null ? '—' : String(today),
        percent: today ?? 0,
        delta,
    };
});

const summaryCards = computed(() =>
    props.metrics.map((entry) => {
        const today = metricValue(props.metrics, entry.metric.key);

        return {
            key: entry.metric.key,
            label: metricLabel(entry.metric.key, entry.metric.label),
            unit: entry.metric.unit,
            display: formatDisplay(entry.metric.key, today),
            delta: deltaInfo(entry.metric.key),
            icon: metricIcons[entry.metric.key] ?? Activity,
            showUnit:
                today !== null &&
                entry.metric.key !== 'sleep_minutes' &&
                entry.metric.key !== 'pain_level' &&
                entry.metric.key !== 'fatigue_level',
        };
    }),
);

const sleepHours = computed({
    get(): string {
        const minutes = Number(values.value.sleep_minutes || 0);

        if (!values.value.sleep_minutes) {
            return '';
        }

        return String(Math.floor(minutes / 60));
    },
    set(hours: string): void {
        const h = Number(hours || 0);
        const m = Number(sleepMinutesPart.value || 0);
        values.value.sleep_minutes = String(h * 60 + m);
    },
});

const sleepMinutesPart = computed({
    get(): string {
        const minutes = Number(values.value.sleep_minutes || 0);

        if (!values.value.sleep_minutes) {
            return '';
        }

        return String(minutes % 60);
    },
    set(mins: string): void {
        const h = Number(sleepHours.value || 0);
        const m = Number(mins || 0);
        values.value.sleep_minutes = String(h * 60 + m);
    },
});

function stepValue(key: string, delta: number, step = 1): void {
    const current = Number(values.value[key] || 0);
    const next = Math.max(0, current + delta * step);
    values.value[key] = String(
        Number.isInteger(step) ? Math.round(next) : Math.round(next * 10) / 10,
    );
}

function setScale(key: string, value: number): void {
    values.value[key] = String(value);
}

const chartOption = computed<EChartsCoreOption>(() => {
    const dates = Array.from(
        new Set(
            Object.values(props.chartSeries)
                .flat()
                .map((point) => point.date),
        ),
    ).sort();

    const seriesFor = (key: string, color: string, name: string) => {
        const map = new Map(
            (props.chartSeries[key] ?? []).map((point) => [
                point.date,
                Number(point.value),
            ]),
        );

        return {
            name,
            type: 'line' as const,
            smooth: true,
            symbol: 'circle',
            symbolSize: 6,
            data: dates.map((date) => map.get(date) ?? null),
            lineStyle: { color, width: 2 },
            itemStyle: { color },
        };
    };

    return {
        grid: { left: 48, right: 48, top: 40, bottom: 32 },
        tooltip: { trigger: 'axis' },
        legend: {
            top: 0,
            textStyle: { color: '#5c5a6e', fontSize: 11 },
        },
        xAxis: {
            type: 'category',
            data: dates,
            axisLabel: { color: '#5c5a6e', fontSize: 11 },
            axisLine: { lineStyle: { color: '#cfc8d8' } },
        },
        yAxis: [
            {
                type: 'value',
                name: 'kg / km/h',
                axisLabel: { color: '#5c5a6e', fontSize: 11 },
                splitLine: {
                    lineStyle: { color: '#cfc8d8', opacity: 0.45 },
                },
            },
            {
                type: 'value',
                name: '分',
                axisLabel: { color: '#5c5a6e', fontSize: 11 },
                splitLine: { show: false },
            },
        ],
        series: [
            {
                ...seriesFor('weight', '#5b5577', '体重'),
                yAxisIndex: 0,
            },
            {
                ...seriesFor('sleep_minutes', '#2b8fef', '睡眠時間'),
                yAxisIndex: 1,
            },
            {
                ...seriesFor('pitch_speed_max', '#29a35c', '最高球速'),
                yAxisIndex: 0,
            },
        ],
    };
});

async function saveAll(): Promise<void> {
    saving.value = true;
    saveMessage.value = null;

    const records = props.metrics
        .filter(
            (entry) =>
                String(values.value[entry.metric.key] ?? '').trim() !== '',
        )
        .map((entry) => ({
            metric_key: entry.metric.key,
            value: Number(String(values.value[entry.metric.key]).trim()),
            note: String(notes.value[entry.metric.key] ?? '').trim() || null,
        }));

    if (records.length === 0) {
        saveMessage.value = '入力された項目がありません。';
        saving.value = false;

        return;
    }

    if (reflection.value.trim() !== '') {
        const first = records[0];
        first.note = [first.note, `振り返り: ${reflection.value.trim()}`]
            .filter(Boolean)
            .join(' / ');
    }

    try {
        await apiFetch('/records/daily', {
            method: 'PUT',
            body: JSON.stringify({
                recorded_on: props.date,
                records,
            }),
        });

        saveMessage.value = '保存しました。';
        router.reload({
            only: ['metrics', 'previousMetrics', 'chartSeries', 'date'],
        });
    } catch {
        saveMessage.value = '保存に失敗しました。';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Head title="コンディション管理" />

    <div class="flex h-full flex-1 flex-col rounded-xl p-4 md:px-6 md:pb-6">
        <div class="flex w-full flex-1 flex-col gap-4 md:gap-5">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(280px,0.8fr)]">
                <PageSectionCard>
                    <div class="flex flex-col gap-3">
                        <Link
                            :href="`/records?date=${date}`"
                            class="inline-flex items-center gap-2 font-sans text-sm font-medium text-cd-ink-muted transition-colors hover:text-primary"
                        >
                            <ArrowLeft :size="16" :stroke-width="1.6" />
                            パフォーマンス管理
                        </Link>
                        <PageTitleOrnament
                            title="コンディション管理"
                            subtitle="体調・回復・パフォーマンスをまとめて記録"
                            align="left"
                        />
                    </div>
                </PageSectionCard>

                <PageSectionCard
                    padding="sm"
                    class="flex items-center justify-center"
                >
                    <DateNavigator
                        :date="date"
                        route-url="/records/condition"
                        :reload-only="[
                            'metrics',
                            'previousMetrics',
                            'chartSeries',
                            'date',
                        ]"
                    />
                </PageSectionCard>
            </div>

            <div
                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                aria-label="本日のコンディションサマリ"
            >
                <PageSectionCard
                    padding="none"
                    class="border-primary/30 bg-primary/5"
                >
                    <div class="flex h-full flex-col justify-between gap-3 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <p
                                class="font-sans text-xs font-medium text-cd-ink-muted"
                            >
                                総合コンディション
                            </p>
                            <span
                                class="flex size-8 items-center justify-center rounded-full bg-primary/15 text-primary"
                            >
                                <Sparkles :size="16" :stroke-width="1.6" />
                            </span>
                        </div>
                        <div>
                            <p class="font-sans text-3xl font-bold text-cd-ink">
                                {{ overall.display }}
                                <span
                                    v-if="overall.score !== null"
                                    class="text-base font-medium text-cd-ink-muted"
                                    >/ 100</span
                                >
                            </p>
                            <div
                                class="mt-2 h-2 overflow-hidden rounded-full bg-cd-line/40"
                            >
                                <div
                                    class="h-full rounded-full bg-primary transition-[width]"
                                    :style="{ width: `${overall.percent}%` }"
                                />
                            </div>
                            <p
                                v-if="overall.delta"
                                class="mt-1.5 font-sans text-xs font-medium"
                                :class="toneClass(overall.delta.tone)"
                            >
                                {{ overall.delta.text }}
                            </p>
                        </div>
                    </div>
                </PageSectionCard>

                <PageSectionCard
                    v-for="card in summaryCards"
                    :key="card.key"
                    padding="none"
                >
                    <div class="flex h-full flex-col justify-between gap-3 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <p
                                class="font-sans text-xs font-medium text-cd-ink-muted"
                            >
                                {{ card.label }}
                            </p>
                            <span
                                class="flex size-8 items-center justify-center rounded-full bg-primary/10 text-primary"
                            >
                                <component
                                    :is="card.icon"
                                    :size="16"
                                    :stroke-width="1.6"
                                />
                            </span>
                        </div>
                        <div>
                            <p
                                class="font-sans text-2xl font-semibold text-cd-ink"
                            >
                                {{ card.display }}
                                <span
                                    v-if="card.showUnit"
                                    class="text-sm font-medium text-cd-ink-muted"
                                    >{{ card.unit }}</span
                                >
                            </p>
                            <p
                                v-if="card.delta"
                                class="mt-1.5 font-sans text-xs font-medium"
                                :class="toneClass(card.delta.tone)"
                            >
                                {{ card.delta.text }}
                            </p>
                        </div>
                    </div>
                </PageSectionCard>
            </div>

            <PageSectionCard aria-label="7日間の推移">
                <h2 class="mb-3 font-sans text-base font-semibold text-cd-ink">
                    7日間の推移
                </h2>
                <BaseChart :option="chartOption" />
            </PageSectionCard>

            <PageSectionCard
                padding="none"
                aria-label="今日のコンディションを記録"
            >
                <div class="border-b border-cd-line px-5 py-4">
                    <h2 class="font-sans text-base font-semibold text-cd-ink">
                        今日のコンディションを記録
                    </h2>
                </div>

                <ul class="grid gap-3 p-4 sm:grid-cols-2">
                    <li
                        v-for="entry in metrics"
                        :key="entry.metric.key"
                        class="rounded-xl border border-cd-line bg-cd-surface p-4"
                    >
                        <div class="flex flex-col gap-3">
                            <div>
                                <Label
                                    :for="`metric-${entry.metric.key}`"
                                    class="font-sans text-sm font-semibold text-cd-ink"
                                >
                                    {{
                                        metricLabel(
                                            entry.metric.key,
                                            entry.metric.label,
                                        )
                                    }}
                                    <span
                                        class="ml-1 text-xs font-normal text-cd-ink-muted"
                                        >({{ entry.metric.unit }})</span
                                    >
                                </Label>
                                <p
                                    v-if="deltaInfo(entry.metric.key)"
                                    class="mt-1 font-sans text-xs font-medium"
                                    :class="
                                        toneClass(
                                            deltaInfo(entry.metric.key)!.tone,
                                        )
                                    "
                                >
                                    {{ deltaInfo(entry.metric.key)!.text }}
                                </p>
                            </div>

                            <template
                                v-if="entry.metric.key === 'sleep_minutes'"
                            >
                                <div class="flex items-center gap-2">
                                    <Input
                                        v-model="sleepHours"
                                        type="text"
                                        inputmode="numeric"
                                        placeholder="時"
                                        class="text-center"
                                    />
                                    <span class="font-sans text-sm">時間</span>
                                    <Input
                                        v-model="sleepMinutesPart"
                                        type="text"
                                        inputmode="numeric"
                                        placeholder="分"
                                        class="text-center"
                                    />
                                    <span class="font-sans text-sm">分</span>
                                </div>
                            </template>

                            <template
                                v-else-if="
                                    entry.metric.value_type === 'scale_1_5'
                                "
                            >
                                <div class="flex flex-wrap gap-1.5">
                                    <Button
                                        v-for="n in 5"
                                        :key="n"
                                        type="button"
                                        size="sm"
                                        :variant="
                                            values[entry.metric.key] ===
                                            String(n)
                                                ? 'default'
                                                : 'outline'
                                        "
                                        class="min-w-9 font-sans"
                                        @click="setScale(entry.metric.key, n)"
                                    >
                                        {{ n }}
                                    </Button>
                                </div>
                            </template>

                            <template v-else>
                                <div class="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="outline"
                                        :aria-label="`${metricLabel(entry.metric.key, entry.metric.label)} を減らす`"
                                        @click="
                                            stepValue(
                                                entry.metric.key,
                                                -1,
                                                entry.metric.value_type ===
                                                    'decimal'
                                                    ? 0.1
                                                    : 1,
                                            )
                                        "
                                    >
                                        <Minus
                                            :size="14"
                                            :stroke-width="1.6"
                                        />
                                    </Button>
                                    <Input
                                        :id="`metric-${entry.metric.key}`"
                                        v-model="values[entry.metric.key]"
                                        type="text"
                                        :inputmode="
                                            entry.metric.value_type ===
                                            'decimal'
                                                ? 'decimal'
                                                : 'numeric'
                                        "
                                        class="text-center"
                                        :placeholder="entry.metric.unit"
                                    />
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="outline"
                                        :aria-label="`${metricLabel(entry.metric.key, entry.metric.label)} を増やす`"
                                        @click="
                                            stepValue(
                                                entry.metric.key,
                                                1,
                                                entry.metric.value_type ===
                                                    'decimal'
                                                    ? 0.1
                                                    : 1,
                                            )
                                        "
                                    >
                                        <Plus
                                            :size="14"
                                            :stroke-width="1.6"
                                        />
                                    </Button>
                                </div>
                            </template>

                            <Input
                                v-model="notes[entry.metric.key]"
                                type="text"
                                placeholder="メモ（任意）"
                                class="text-sm"
                            />
                        </div>
                    </li>

                    <li
                        class="flex flex-col rounded-xl border border-cd-line bg-cd-surface p-4 sm:col-span-2"
                    >
                        <Label
                            for="reflection-note"
                            class="font-sans text-sm font-semibold text-cd-ink"
                            >今日の振り返りメモ</Label
                        >
                        <textarea
                            id="reflection-note"
                            v-model="reflection"
                            rows="3"
                            maxlength="500"
                            class="mt-3 min-h-24 w-full flex-1 rounded-md border border-input bg-transparent px-3 py-2 font-sans text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            placeholder="気づきや体調のメモ（任意）"
                        />
                        <p
                            class="mt-1 text-right font-sans text-xs text-cd-ink-muted"
                        >
                            {{ reflection.length }}/500
                        </p>
                    </li>
                </ul>
            </PageSectionCard>

            <div class="flex items-center justify-between gap-3">
                <p
                    v-if="saveMessage"
                    class="font-sans text-sm"
                    :class="
                        saveMessage.includes('失敗')
                            ? 'text-destructive'
                            : 'text-cd-moss'
                    "
                >
                    {{ saveMessage }}
                </p>
                <span v-else />
                <div class="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="font-sans"
                        as-child
                    >
                        <Link :href="`/records?date=${date}`">キャンセル</Link>
                    </Button>
                    <Button
                        type="button"
                        class="font-sans tracking-[0.08em]"
                        :disabled="saving"
                        @click="saveAll"
                    >
                        記録を保存
                        <ArrowRight :size="16" :stroke-width="1.6" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
