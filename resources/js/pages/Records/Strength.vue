<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import type { EChartsCoreOption } from 'echarts/core';
import { computed, ref, watch } from 'vue';
import BaseChart from '@/components/charts/BaseChart.vue';
import PageSectionCard from '@/components/PageSectionCard.vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Period = 'week' | 'month' | '3months' | 'year' | null;

type StrengthPoint = {
    date: string;
    item_name: string;
    max_load_value: string | null;
};

interface Props {
    from: string;
    to: string;
    period: Period;
    chartPoints: StrengthPoint[];
}

const props = defineProps<Props>();

const filterFrom = ref(props.from);
const filterTo = ref(props.to);
const selectedPeriod = ref<Period>(props.period);

watch(
    () => [props.from, props.to, props.period] as const,
    ([from, to, period]) => {
        filterFrom.value = from;
        filterTo.value = to;
        selectedPeriod.value = period;
    },
);

const periodOptions: { value: Exclude<Period, null>; label: string }[] = [
    { value: 'week', label: '週' },
    { value: 'month', label: '月' },
    { value: '3months', label: '3ヶ月' },
    { value: 'year', label: '年' },
];

const chartColors = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
];

const itemNames = computed(() =>
    [...new Set(props.chartPoints.map((point) => point.item_name))].sort(),
);

const dates = computed(() =>
    [...new Set(props.chartPoints.map((point) => point.date))].sort(),
);

const chartOption = computed<EChartsCoreOption>(() => ({
    grid: { left: 48, right: 24, top: 40, bottom: 32 },
    legend: {
        top: 0,
        textStyle: { color: 'var(--cd-ink-muted)', fontSize: 11 },
    },
    tooltip: {
        trigger: 'axis',
    },
    xAxis: {
        type: 'category',
        data: dates.value,
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
        name: 'kg',
        axisLabel: {
            color: 'var(--cd-ink-muted)',
            fontSize: 11,
        },
        splitLine: {
            lineStyle: { color: 'var(--cd-line)', opacity: 0.4 },
        },
    },
    series: itemNames.value.map((itemName, index) => {
        const byDate = new Map(
            props.chartPoints
                .filter((point) => point.item_name === itemName)
                .map((point) => [point.date, point.max_load_value]),
        );
        const color = chartColors[index % chartColors.length];

        return {
            name: itemName,
            type: 'line' as const,
            smooth: true,
            symbol: 'circle',
            symbolSize: 6,
            connectNulls: false,
            data: dates.value.map((date) => {
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

function navigate(query: Record<string, string>): void {
    router.get('/records/strength', query, {
        preserveState: true,
        preserveScroll: true,
    });
}

function applyDateFilter(): void {
    selectedPeriod.value = null;
    navigate({
        from: filterFrom.value,
        to: filterTo.value,
    });
}

function applyPeriod(period: Exclude<Period, null>): void {
    selectedPeriod.value = period;
    navigate({
        period,
        to: filterTo.value,
    });
}
</script>

<template>
    <Head title="筋力チャート" />

    <div class="flex h-full flex-1 flex-col rounded-xl p-4 md:px-6 md:pb-6">
        <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-4">
            <PageSectionCard>
                <div class="flex flex-col gap-4">
                    <Link
                        href="/records"
                        class="inline-flex items-center gap-2 font-sans text-sm font-medium text-cd-ink-muted transition-colors hover:text-primary"
                    >
                        <ArrowLeft :size="16" :stroke-width="1.6" />
                        記録トップ
                    </Link>

                    <PageTitleOrnament
                        title="筋力チャート"
                        subtitle="完了セッションの種目別・日次最大負荷"
                        align="left"
                    />
                </div>
            </PageSectionCard>

            <PageSectionCard aria-label="期間フィルター" padding="sm">
                <div class="flex flex-col gap-3">
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-for="option in periodOptions"
                            :key="option.value"
                            type="button"
                            size="sm"
                            :variant="
                                selectedPeriod === option.value
                                    ? 'default'
                                    : 'outline'
                            "
                            @click="applyPeriod(option.value)"
                        >
                            {{ option.label }}
                        </Button>
                    </div>

                    <div class="flex flex-wrap items-end gap-3">
                        <div class="flex flex-col gap-1">
                            <label
                                for="strength-from-date"
                                class="font-sans text-xs text-cd-ink-muted"
                            >
                                開始日
                            </label>
                            <Input
                                id="strength-from-date"
                                v-model="filterFrom"
                                type="date"
                                class="w-40"
                            />
                        </div>
                        <div class="flex flex-col gap-1">
                            <label
                                for="strength-to-date"
                                class="font-sans text-xs text-cd-ink-muted"
                            >
                                終了日
                            </label>
                            <Input
                                id="strength-to-date"
                                v-model="filterTo"
                                type="date"
                                class="w-40"
                            />
                        </div>
                        <Button
                            type="button"
                            size="sm"
                            @click="applyDateFilter"
                        >
                            適用
                        </Button>
                    </div>
                </div>
            </PageSectionCard>

            <PageSectionCard aria-label="筋力推移グラフ">
                <h2 class="mb-4 font-sans text-base font-semibold text-cd-ink">
                    種目別 最大負荷
                </h2>
                <BaseChart
                    v-if="chartPoints.length > 0"
                    :option="chartOption"
                />
                <p
                    v-else
                    class="py-12 text-center font-sans text-sm text-cd-ink-muted"
                >
                    この期間に完了した筋力セッションがありません。
                </p>
            </PageSectionCard>
        </div>
    </div>
</template>
