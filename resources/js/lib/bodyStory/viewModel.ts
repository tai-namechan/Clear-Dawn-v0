import type {
    BodyStoryExportPayload,
    NutritionStoryViewModel,
    StoryChartPoint,
    WeeklyStoryViewModel,
    WeightStoryViewModel,
} from '@/types/bodyStory';

function dottedDate(isoDate: string): string {
    const [year, month, day] = isoDate.split('-');

    return `${year}.${month}.${day}`;
}

function axisLabel(isoDate: string): string {
    const parts = isoDate.split('-');
    const month = Number(parts[1]);
    const day = Number(parts[2]);

    return `${month}/${day}`;
}

function formatKcal(value: number): string {
    return `${Math.round(value).toLocaleString('ja-JP')} kcal`;
}

function formatKg(value: number): string {
    return `${value.toLocaleString('ja-JP', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
    })} kg`;
}

function formatDeltaKg(value: number): string {
    if (Object.is(value, -0) || value === 0) {
        return formatKg(0);
    }

    const sign = value > 0 ? '+' : '';

    return `${sign}${formatKg(value)}`;
}

export function macroPercents(
    proteinG: number | null,
    fatG: number | null,
    carbsG: number | null,
): { p: number | null; f: number | null; c: number | null } {
    if (proteinG === null && fatG === null && carbsG === null) {
        return { p: null, f: null, c: null };
    }

    const proteinKcal = (proteinG ?? 0) * 4;
    const fatKcal = (fatG ?? 0) * 9;
    const carbKcal = (carbsG ?? 0) * 4;
    const total = proteinKcal + fatKcal + carbKcal;

    if (total <= 0) {
        return { p: null, f: null, c: null };
    }

    return {
        p: Math.round((proteinKcal / total) * 100),
        f: Math.round((fatKcal / total) * 100),
        c: Math.round((carbKcal / total) * 100),
    };
}

function caloriePoints(
    history: Array<{ date: string; calories_kcal: number }>,
): StoryChartPoint[] {
    return history.map((point) => ({
        label: axisLabel(point.date),
        value: point.calories_kcal,
    }));
}

function weightPoints(
    history: Array<{ date: string; weight_kg: number }>,
): StoryChartPoint[] {
    return history.map((point) => ({
        label: axisLabel(point.date),
        value: point.weight_kg,
    }));
}

export function toNutritionViewModel(
    payload: BodyStoryExportPayload,
): NutritionStoryViewModel {
    const percents = macroPercents(
        payload.nutrition.protein_g,
        payload.nutrition.fat_g,
        payload.nutrition.carbs_g,
    );

    return {
        displayDate: dottedDate(payload.date),
        calories: {
            value: payload.nutrition.calories_kcal,
            display:
                payload.nutrition.calories_kcal !== null
                    ? formatKcal(payload.nutrition.calories_kcal)
                    : null,
        },
        averageCalories: {
            value: payload.nutrition.average_calories_kcal,
            display:
                payload.nutrition.average_calories_kcal !== null
                    ? formatKcal(payload.nutrition.average_calories_kcal)
                    : null,
        },
        protein: {
            grams: payload.nutrition.protein_g,
            percentage: percents.p,
        },
        fat: {
            grams: payload.nutrition.fat_g,
            percentage: percents.f,
        },
        carbs: {
            grams: payload.nutrition.carbs_g,
            percentage: percents.c,
        },
        chart: { points: caloriePoints(payload.nutrition.calorie_history) },
    };
}

export function toWeightViewModel(
    payload: BodyStoryExportPayload,
): WeightStoryViewModel {
    return {
        displayDate: dottedDate(payload.date),
        weight: {
            value: payload.weight.weight_kg,
            display:
                payload.weight.weight_kg !== null
                    ? formatKg(payload.weight.weight_kg)
                    : null,
        },
        delta: {
            value: payload.weight.delta_kg,
            display:
                payload.weight.delta_kg !== null
                    ? formatDeltaKg(payload.weight.delta_kg)
                    : null,
        },
        chart: { points: weightPoints(payload.weight.history) },
    };
}

export function toWeeklyViewModel(
    payload: BodyStoryExportPayload,
): WeeklyStoryViewModel {
    const percents = macroPercents(
        payload.nutrition.average_protein_g,
        payload.nutrition.average_fat_g,
        payload.nutrition.average_carbs_g,
    );

    return {
        displayDate: `${dottedDate(payload.start_date)} - ${dottedDate(payload.end_date)}`,
        averageCalories: {
            value: payload.nutrition.average_calories_kcal,
            display:
                payload.nutrition.average_calories_kcal !== null
                    ? formatKcal(payload.nutrition.average_calories_kcal)
                    : null,
        },
        averageWeight: {
            value: payload.weight.average_7d_kg,
            display:
                payload.weight.average_7d_kg !== null
                    ? formatKg(payload.weight.average_7d_kg)
                    : null,
        },
        protein: {
            grams: payload.nutrition.average_protein_g,
            percentage: percents.p,
        },
        fat: {
            grams: payload.nutrition.average_fat_g,
            percentage: percents.f,
        },
        carbs: {
            grams: payload.nutrition.average_carbs_g,
            percentage: percents.c,
        },
        calorieChart: {
            points: caloriePoints(payload.nutrition.calorie_history),
        },
        weightChart: { points: weightPoints(payload.weight.history) },
    };
}

export function storyFilename(payload: BodyStoryExportPayload): string {
    if (payload.kind === 'weekly') {
        return `clear-dawn-weekly-${payload.start_date}-${payload.end_date}.png`;
    }

    if (payload.kind === 'weight') {
        return `clear-dawn-body-${payload.date}.png`;
    }

    return `clear-dawn-nutrition-${payload.date}.png`;
}
