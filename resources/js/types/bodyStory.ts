export type BodyStoryKind = 'nutrition' | 'weight' | 'weekly' | 'body';

export type BodyStorySegmentKey =
    | 'left_arm'
    | 'right_arm'
    | 'torso'
    | 'left_leg'
    | 'right_leg';

export type BodyStorySegmentValues = {
    lean_mass_kg: number | null;
    fat_mass_kg: number | null;
};

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
    body: {
        weight_kg: number | null;
        lean_body_mass_kg: number | null;
        skeletal_muscle_mass_kg: number | null;
        body_fat_percentage: number | null;
        abdominal_circumference_cm: number | null;
        measured_on: string | null;
        segments: Record<BodyStorySegmentKey, BodyStorySegmentValues>;
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

export type BodyCompositionStoryViewModel = {
    displayDate: string | null;
    weight: { value: number | null; display: string };
    skeletalMuscle: { value: number | null; display: string };
    bodyFat: { value: number | null; display: string };
    abdominal: { value: number | null; display: string };
    segments: Record<
        BodyStorySegmentKey,
        { lean: string; fat: string }
    >;
};
