<script setup lang="ts">
import type { EChartsCoreOption } from 'echarts/core';
import BaseChart from '@/components/charts/BaseChart.vue';

type DeltaTone = 'good' | 'bad' | 'neutral';

interface DeltaInfo {
    text: string;
    tone: DeltaTone;
}

interface StatusCard {
    key: string;
    label: string;
    display: string;
    delta: DeltaInfo | null;
}

defineProps<{
    hasChartData: boolean;
    chartOption: EChartsCoreOption;
    statusCards: StatusCard[];
}>();

function toneClass(tone: DeltaTone): string {
    if (tone === 'good') {
        return 'text-cd-moss';
    }

    if (tone === 'bad') {
        return 'text-cd-danger';
    }

    return 'text-cd-ink-muted';
}
</script>

<template>
    <div class="flex flex-col gap-4" aria-label="7日間の推移">
        <div>
            <h2 class="mb-1 font-sans text-base font-semibold text-cd-ink">
                7日間の推移
            </h2>
            <p class="mb-4 font-sans text-xs text-cd-ink-muted">
                体重・睡眠・最高球速の変化を確認します
            </p>

            <div
                v-if="!hasChartData"
                class="rounded-xl border border-dashed border-cd-line px-4 py-12 text-center"
            >
                <p class="font-sans text-sm text-cd-ink-muted">
                    まだ推移データがありません。「今日」タブで記録するとここにグラフが表示されます。
                </p>
            </div>
            <BaseChart v-else :option="chartOption" />
        </div>

        <div
            class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
            aria-label="前日比サマリ"
        >
            <div
                v-for="card in statusCards"
                :key="`trend-${card.key}`"
                class="flex h-full flex-col justify-between gap-2 rounded-xl border border-cd-line bg-cd-surface p-4"
            >
                <p class="font-sans text-xs font-medium text-cd-ink-muted">
                    {{ card.label }}
                </p>
                <p class="font-sans text-xl font-semibold text-cd-ink">
                    {{ card.display }}
                </p>
                <p
                    v-if="card.delta"
                    class="font-sans text-xs font-medium"
                    :class="toneClass(card.delta.tone)"
                >
                    {{ card.delta.text }}
                </p>
            </div>
        </div>
    </div>
</template>
