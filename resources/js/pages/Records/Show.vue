<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Trash2 } from '@lucide/vue';
import type { EChartsCoreOption } from 'echarts/core';
import { computed, ref } from 'vue';
import BaseChart from '@/components/charts/BaseChart.vue';
import PageSectionCard from '@/components/PageSectionCard.vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { apiFetch } from '@/lib/apiFetch';
import type { ChartPoint, Metric, MetricRecord } from '@/types/routine';

interface Props {
    metric: Metric;
    from: string;
    to: string;
    records: MetricRecord[];
    chartPoints: ChartPoint[];
}

const props = defineProps<Props>();

const filterFrom = ref(props.from);
const filterTo = ref(props.to);

const chartOption = computed<EChartsCoreOption>(() => ({
    grid: { left: 48, right: 24, top: 24, bottom: 32 },
    tooltip: {
        trigger: 'axis',
    },
    xAxis: {
        type: 'category',
        data: props.chartPoints.map((point) => point.date),
        axisLabel: {
            color: 'var(--cd-ink-muted)',
            fontSize: 11,
        },
        axisLine: {
            lineStyle: { color: 'var(--cd-line)' },
        },
    },
    yAxis: {
        type: 'value',
        axisLabel: {
            color: 'var(--cd-ink-muted)',
            fontSize: 11,
        },
        splitLine: {
            lineStyle: { color: 'var(--cd-line)', opacity: 0.4 },
        },
    },
    series: [
        {
            type: 'line',
            smooth: true,
            symbol: 'circle',
            symbolSize: 6,
            data: props.chartPoints.map((point) => Number(point.value)),
            lineStyle: {
                color: 'var(--chart-1)',
                width: 2,
            },
            itemStyle: {
                color: 'var(--chart-1)',
            },
            areaStyle: {
                color: 'color-mix(in oklab, var(--chart-1) 12%, transparent)',
            },
        },
    ],
}));

function applyDateFilter(): void {
    router.get(
        `/records/${props.metric.key}`,
        { from: filterFrom.value, to: filterTo.value },
        { preserveState: true, preserveScroll: true },
    );
}

async function deleteRecord(record: MetricRecord): Promise<void> {
    if (!confirm(`${record.recorded_on} の記録を削除しますか？`)) {
        return;
    }

    await apiFetch(`/records/${props.metric.key}/${record.id}`, {
        method: 'DELETE',
    });

    router.reload({ only: ['records', 'chartPoints'] });
}
</script>

<template>
    <Head :title="metric.label" />

    <div
        class="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4 md:px-6 md:pb-6"
    >
        <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-4">
            <PageSectionCard>
                <div class="flex flex-col gap-4">
                    <Link
                        href="/records"
                        class="inline-flex items-center gap-2 font-sans text-sm font-medium text-cd-ink-muted transition-colors hover:text-primary"
                    >
                        <ArrowLeft :size="16" :stroke-width="1.6" />
                        コンディション管理
                    </Link>

                    <PageTitleOrnament
                        :title="metric.label"
                        :subtitle="`単位: ${metric.unit}`"
                        align="left"
                    />
                </div>
            </PageSectionCard>

            <PageSectionCard aria-label="期間フィルター" padding="sm">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex flex-col gap-1">
                        <label
                            for="from-date"
                            class="font-sans text-xs text-cd-ink-muted"
                        >
                            開始日
                        </label>
                        <Input
                            id="from-date"
                            v-model="filterFrom"
                            type="date"
                            class="w-40"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label
                            for="to-date"
                            class="font-sans text-xs text-cd-ink-muted"
                        >
                            終了日
                        </label>
                        <Input
                            id="to-date"
                            v-model="filterTo"
                            type="date"
                            class="w-40"
                        />
                    </div>
                    <Button type="button" size="sm" @click="applyDateFilter">
                        適用
                    </Button>
                </div>
            </PageSectionCard>

            <PageSectionCard aria-label="推移グラフ">
                <h2 class="mb-4 font-sans text-base font-semibold text-cd-ink">
                    推移
                </h2>
                <BaseChart
                    v-if="chartPoints.length > 0"
                    :option="chartOption"
                />
                <p
                    v-else
                    class="py-12 text-center font-sans text-sm text-cd-ink-muted"
                >
                    この期間にデータがありません。
                </p>
            </PageSectionCard>

            <PageSectionCard padding="none" aria-label="記録一覧">
                <h2
                    class="border-b border-cd-line px-5 py-4 font-sans text-base font-semibold text-cd-ink"
                >
                    記録一覧
                </h2>

                <div class="overflow-x-auto">
                    <table
                        class="w-full min-w-[480px] text-left font-sans text-sm"
                    >
                        <thead>
                            <tr
                                class="border-b border-cd-line bg-white/40 text-xs tracking-[0.06em] text-cd-ink-muted"
                            >
                                <th class="px-4 py-3 font-medium">日付</th>
                                <th class="px-4 py-3 font-medium">値</th>
                                <th class="px-4 py-3 font-medium">メモ</th>
                                <th class="px-4 py-3 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="record in records"
                                :key="record.id"
                                class="border-b border-cd-line/40 last:border-b-0"
                            >
                                <td class="px-4 py-3 text-cd-ink">
                                    {{ record.recorded_on }}
                                </td>
                                <td class="px-4 py-3 text-cd-ink">
                                    {{ record.value }}
                                    {{ metric.unit }}
                                </td>
                                <td class="px-4 py-3 text-cd-ink-muted">
                                    {{ record.note ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        aria-label="記録を削除"
                                        @click="deleteRecord(record)"
                                    >
                                        <Trash2
                                            :size="14"
                                            :stroke-width="1.6"
                                        />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p
                    v-if="records.length === 0"
                    class="px-5 py-12 text-center font-sans text-sm text-cd-ink-muted"
                >
                    記録がありません。
                </p>
            </PageSectionCard>
        </div>
    </div>
</template>
