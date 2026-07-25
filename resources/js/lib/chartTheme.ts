import type { NutritionChartPoint } from '@/types/routine';

/** ECharts 向け hex（CSS 変数は ECharts が解決できないため） */
export const CHART_COLORS = {
    primary: '#5B5577',
    secondary: '#4D8FCB',
    tertiary: '#81779F',
    light: '#AAA3BD',
    protein: '#3F9A70',
    fat: '#D58A38',
    carbs: '#3B82C4',
    grid: '#E9E6EC',
    axis: '#CEC9D3',
    label: '#716D79',
    primaryArea: 'rgba(91, 85, 119, 0.07)',
} as const;

/** 筋力チャート系列（紫系のみ・黒禁止） */
export const STRENGTH_SERIES_COLORS = [
    CHART_COLORS.primary,
    CHART_COLORS.tertiary,
    CHART_COLORS.light,
    CHART_COLORS.secondary,
] as const;

export function eachDateInclusive(from: string, to: string): string[] {
    if (!from || !to || from > to) {
        return [];
    }

    const dates: string[] = [];
    let cursor = from;

    while (cursor <= to) {
        dates.push(cursor);
        const [year, month, day] = cursor.split('-').map(Number);
        const next = new Date(Date.UTC(year, month - 1, day + 1));
        cursor = next.toISOString().slice(0, 10);
    }

    return dates;
}

export function chartAxisLabel(fontSize = 10): {
    color: string;
    fontSize: number;
} {
    return {
        color: CHART_COLORS.label,
        fontSize,
    };
}

export function chartAxisLine(): { lineStyle: { color: string } } {
    return {
        lineStyle: { color: CHART_COLORS.axis },
    };
}

export function chartSplitLine(): {
    lineStyle: { color: string };
} {
    return {
        lineStyle: { color: CHART_COLORS.grid },
    };
}

export function chartLegend(fontSize = 10): Record<string, unknown> {
    return {
        top: 0,
        right: 0,
        icon: 'circle',
        itemWidth: 8,
        itemHeight: 8,
        itemGap: 12,
        textStyle: {
            color: CHART_COLORS.label,
            fontSize,
        },
    };
}

export function chartLineSeriesStyle(color: string): {
    symbol: 'circle';
    symbolSize: number;
    showSymbol: boolean;
    connectNulls: false;
    lineStyle: { color: string; width: number };
    itemStyle: { color: string };
    emphasis: { scale: number };
} {
    return {
        symbol: 'circle',
        symbolSize: 6,
        showSymbol: true,
        connectNulls: false,
        lineStyle: { color, width: 2 },
        itemStyle: { color },
        emphasis: { scale: 10 / 6 },
    };
}

type NutritionValueKey = 'kcal' | 'protein_g' | 'fat_g' | 'carb_g';

/**
 * 期間内の全日を軸にし、未記録日は null（0 で結ばない）。
 */
export function nutritionSeriesByDate(
    points: NutritionChartPoint[],
    from: string,
    to: string,
    key: NutritionValueKey,
): { dates: string[]; values: Array<number | null> } {
    const byDate = new Map(points.map((point) => [point.date, point]));
    const dates = eachDateInclusive(from, to);

    return {
        dates,
        values: dates.map((date) => {
            const point = byDate.get(date);

            return point ? Number(point[key]) : null;
        }),
    };
}
