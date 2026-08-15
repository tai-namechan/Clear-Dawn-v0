export type BodyStoryKind = 'nutrition' | 'weight' | 'weekly';

export type BodyStoryExportPayload = {
    kind: BodyStoryKind;
    date: string;
    start_date: string;
    end_date: string;
    template_url: string;
    nutrition: {
        calories_kcal: number | null;
        protein_g: number | null;
        fat_g: number | null;
        carbs_g: number | null;
        average_calories_kcal: number | null;
        average_protein_g: number | null;
        average_fat_g: number | null;
        average_carbs_g: number | null;
        calorie_history: Array<{ date: string; calories_kcal: number }>;
    };
    weight: {
        weight_kg: number | null;
        previous_weight_kg: number | null;
        delta_kg: number | null;
        average_7d_kg: number | null;
        history: Array<{ date: string; weight_kg: number }>;
    };
};

export type StoryChartPoint = {
    label: string;
    value: number;
};

export type NutritionStoryViewModel = {
    displayDate: string | null;
    calories: { value: number | null; display: string | null };
    averageCalories: { value: number | null; display: string | null };
    protein: { grams: number | null; percentage: number | null };
    fat: { grams: number | null; percentage: number | null };
    carbs: { grams: number | null; percentage: number | null };
    chart: { points: StoryChartPoint[] };
};

export type WeightStoryViewModel = {
    displayDate: string | null;
    weight: { value: number | null; display: string | null };
    delta: { value: number | null; display: string | null };
    chart: { points: StoryChartPoint[] };
};

export type WeeklyStoryViewModel = {
    displayDate: string | null;
    averageCalories: { value: number | null; display: string | null };
    averageWeight: { value: number | null; display: string | null };
    protein: { grams: number | null; percentage: number | null };
    fat: { grams: number | null; percentage: number | null };
    carbs: { grams: number | null; percentage: number | null };
    calorieChart: { points: StoryChartPoint[] };
    weightChart: { points: StoryChartPoint[] };
};
