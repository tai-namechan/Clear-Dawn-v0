<script setup lang="ts">
import { BarChart, LineChart } from 'echarts/charts';
import { GridComponent, LegendComponent, TooltipComponent } from 'echarts/components';
import type { EChartsCoreOption } from 'echarts/core';
import * as echarts from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { onMounted, onUnmounted, ref, watch } from 'vue';

echarts.use([
    LineChart,
    BarChart,
    GridComponent,
    TooltipComponent,
    LegendComponent,
    CanvasRenderer,
]);

interface Props {
    option: EChartsCoreOption;
    class?: string;
}

const props = defineProps<Props>();

const chartRef = ref<HTMLElement | null>(null);
let chart: echarts.ECharts | null = null;
let observer: ResizeObserver | null = null;

function initChart(): void {
    if (!chartRef.value) {
        return;
    }

    chart?.dispose();
    chart = echarts.init(chartRef.value);
    chart.setOption(props.option);
}

onMounted(() => {
    initChart();

    if (chartRef.value) {
        observer = new ResizeObserver(() => {
            chart?.resize();
        });
        observer.observe(chartRef.value);
    }
});

watch(
    () => props.option,
    (option) => {
        chart?.setOption(option, true);
    },
    { deep: true },
);

onUnmounted(() => {
    observer?.disconnect();
    chart?.dispose();
    chart = null;
});
</script>

<template>
    <div ref="chartRef" :class="['h-64 w-full', props.class]" />
</template>
