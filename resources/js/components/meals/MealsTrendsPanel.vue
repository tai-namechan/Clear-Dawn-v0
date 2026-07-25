<script setup lang="ts">
import type { EChartsCoreOption } from 'echarts/core';
import BaseChart from '@/components/charts/BaseChart.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Props {
    filterFrom: string;
    filterTo: string;
    hasChartData: boolean;
    kcalChartOption: EChartsCoreOption;
    pfcChartOption: EChartsCoreOption;
}

defineProps<Props>();

const emit = defineEmits<{
    'update:filterFrom': [value: string];
    'update:filterTo': [value: string];
    apply: [];
}>();
</script>

<template>
    <div class="flex flex-col gap-4" aria-label="推移">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <h2 class="font-sans text-base font-semibold text-cd-ink">
                    推移
                </h2>
                <p class="mt-1 font-sans text-sm text-cd-ink-muted">
                    期間内の日別合計を表示します。
                </p>
            </div>

            <div class="flex flex-wrap items-end gap-3">
                <div class="flex flex-col gap-1">
                    <Label class="font-sans text-xs">開始</Label>
                    <Input
                        :model-value="filterFrom"
                        type="date"
                        @update:model-value="
                            emit('update:filterFrom', String($event ?? ''))
                        "
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <Label class="font-sans text-xs">終了</Label>
                    <Input
                        :model-value="filterTo"
                        type="date"
                        @update:model-value="
                            emit('update:filterTo', String($event ?? ''))
                        "
                    />
                </div>
                <Button
                    type="button"
                    variant="outline"
                    class="font-sans"
                    @click="emit('apply')"
                >
                    反映
                </Button>
            </div>
        </div>

        <div
            v-if="!hasChartData"
            class="rounded-xl border border-dashed border-cd-line px-4 py-10 text-center"
        >
            <p class="font-sans text-sm text-cd-ink-muted">
                この期間の食事記録がまだありません。記録すると推移グラフが表示されます。
            </p>
        </div>

        <div v-else class="grid gap-6 lg:grid-cols-2">
            <div>
                <h3 class="mb-2 font-sans text-sm font-semibold text-cd-ink">
                    エネルギー（kcal）
                </h3>
                <BaseChart :option="kcalChartOption" />
            </div>
            <div>
                <h3 class="mb-2 font-sans text-sm font-semibold text-cd-ink">
                    PFC（g）
                </h3>
                <BaseChart :option="pfcChartOption" />
            </div>
        </div>
    </div>
</template>
