<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Minus, Plus } from '@lucide/vue';
import type { EChartsCoreOption } from 'echarts/core';
import { computed, ref, watch } from 'vue';
import BaseChart from '@/components/charts/BaseChart.vue';
import DateNavigator from '@/components/DateNavigator.vue';
import PageSectionCard from '@/components/PageSectionCard.vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { apiFetch } from '@/lib/apiFetch';
import type { ChartPoint, DailyMetricEntry } from '@/types/routine';

interface Props {
    date: string;
    metrics: DailyMetricEntry[];
    previousMetrics: DailyMetricEntry[];
    chartSeries: Record<string, ChartPoint[]>;
}

const props = defineProps<Props>();

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
        const hours = Math.floor(value / 60);
        const minutes = Math.round(value % 60);

        return `${hours}h ${String(minutes).padStart(2, '0')}m`;
    }

    return value.toLocaleString('ja-JP', { maximumFractionDigits: 1 });
}

function deltaText(key: string): string | null {
    const today = metricValue(props.metrics, key);
    const prev = metricValue(props.previousMetrics, key);

    if (today === null || prev === null) {
        return null;
    }

    const diff = today - prev;

    if (Math.abs(diff) < 0.05) {
        return '変化なし';
    }

    const sign = diff > 0 ? '▲' : '▼';

    if (key === 'sleep_minutes') {
        const abs = Math.abs(Math.round(diff));

        return `${sign} ${Math.floor(abs / 60)}h ${abs % 60}m`;
    }

    return `${sign} ${Math.abs(diff).toLocaleString('ja-JP', {
        maximumFractionDigits: 1,
    })}`;
}

const summaryCards = computed(() =>
    props.metrics.map((entry) => {
        const today = metricValue(props.metrics, entry.metric.key);

        return {
            key: entry.metric.key,
            label: entry.metric.label,
            unit: entry.metric.unit,
            display: formatDisplay(entry.metric.key, today),
            delta: deltaText(entry.metric.key),
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
            textStyle: { color: 'var(--cd-ink-muted)', fontSize: 11 },
        },
        xAxis: {
            type: 'category',
            data: dates,
            axisLabel: { color: 'var(--cd-ink-muted)', fontSize: 11 },
            axisLine: { lineStyle: { color: 'var(--cd-line)' } },
        },
        yAxis: [
            {
                type: 'value',
                name: 'kg / km/h',
                axisLabel: { color: 'var(--cd-ink-muted)', fontSize: 11 },
                splitLine: {
                    lineStyle: { color: 'var(--cd-line)', opacity: 0.4 },
                },
            },
            {
                type: 'value',
                name: '分',
                axisLabel: { color: 'var(--cd-ink-muted)', fontSize: 11 },
                splitLine: { show: false },
            },
        ],
        series: [
            { ...seriesFor('weight', 'var(--chart-1)', '体重'), yAxisIndex: 0 },
            {
                ...seriesFor('sleep_minutes', 'var(--chart-2)', '睡眠(分)'),
                yAxisIndex: 1,
            },
            {
                ...seriesFor('pitch_speed_max', 'var(--chart-3)', '最高球速'),
                yAxisIndex: 0,
            },
        ],
    };
});

async function saveAll(): Promise<void> {
    saving.value = true;
    saveMessage.value = null;

    const records = props.metrics
        .filter((entry) => String(values.value[entry.metric.key] ?? '').trim() !== '')
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
        <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-4">
            <PageSectionCard>
                <div class="flex flex-col gap-4">
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

            <PageSectionCard padding="sm">
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

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <PageSectionCard
                    v-for="card in summaryCards"
                    :key="card.key"
                    padding="sm"
                >
                    <p class="font-sans text-xs text-cd-ink-muted">
                        {{ card.label }}
                    </p>
                    <p class="mt-1 font-sans text-2xl font-semibold text-cd-ink">
                        {{ card.display }}
                        <span
                            v-if="
                                card.display !== '—' &&
                                card.key !== 'sleep_minutes'
                            "
                            class="text-sm font-medium text-cd-ink-muted"
                            >{{ card.unit }}</span
                        >
                    </p>
                    <p
                        v-if="card.delta"
                        class="mt-1 font-sans text-xs text-cd-ink-muted"
                    >
                        {{ card.delta }}
                    </p>
                </PageSectionCard>
            </div>

            <PageSectionCard aria-label="7日間の推移">
                <h2 class="mb-3 font-sans text-base font-semibold text-cd-ink">
                    7日間の推移
                </h2>
                <BaseChart :option="chartOption" />
            </PageSectionCard>

            <PageSectionCard aria-label="今日のコンディションを記録">
                <h2 class="mb-4 font-sans text-base font-semibold text-cd-ink">
                    今日のコンディションを記録
                </h2>

                <ul class="flex flex-col gap-5">
                    <li
                        v-for="entry in metrics"
                        :key="entry.metric.key"
                        class="border-b border-cd-line pb-5 last:border-b-0 last:pb-0"
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div class="min-w-0 flex-1">
                                <Label
                                    :for="`metric-${entry.metric.key}`"
                                    class="font-sans text-base font-semibold text-cd-ink"
                                >
                                    {{ entry.metric.label }}
                                    <span
                                        class="ml-1 text-xs font-normal text-cd-ink-muted"
                                        >({{ entry.metric.unit }})</span
                                    >
                                </Label>
                                <p
                                    v-if="deltaText(entry.metric.key)"
                                    class="mt-1 font-sans text-xs text-cd-ink-muted"
                                >
                                    前日比 {{ deltaText(entry.metric.key) }}
                                </p>
                            </div>

                            <div class="flex w-full flex-col gap-2 sm:w-64">
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
                                        <span class="font-sans text-sm"
                                            >時間</span
                                        >
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
                                            @click="
                                                setScale(entry.metric.key, n)
                                            "
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
                                            :aria-label="`${entry.metric.label} を減らす`"
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
                                            :aria-label="`${entry.metric.label} を増やす`"
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
                        </div>
                    </li>
                </ul>

                <div class="mt-5 flex flex-col gap-2">
                    <Label class="font-sans text-sm font-semibold text-cd-ink"
                        >今日の振り返りメモ</Label
                    >
                    <textarea
                        v-model="reflection"
                        rows="3"
                        maxlength="500"
                        class="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 font-sans text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        placeholder="気づきや体調のメモ（任意）"
                    />
                    <p class="text-right font-sans text-xs text-cd-ink-muted">
                        {{ reflection.length }}/500
                    </p>
                </div>
            </PageSectionCard>

            <PageSectionCard padding="sm">
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
                            <Link :href="`/records?date=${date}`"
                                >キャンセル</Link
                            >
                        </Button>
                        <Button
                            type="button"
                            class="font-sans tracking-[0.08em]"
                            :disabled="saving"
                            @click="saveAll"
                        >
                            記録を保存
                        </Button>
                    </div>
                </div>
            </PageSectionCard>
        </div>
    </div>
</template>
