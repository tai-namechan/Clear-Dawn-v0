import type { LifeArea } from '@/types/matrix';

export type StepPurpose =
    | 'prep'
    | 'movement'
    | 'power'
    | 'strength'
    | 'care'
    | 'practice'
    | 'study'
    | 'review'
    | 'other';

export type RoutineItemCategory =
    | 'strength'
    | 'baseball'
    | 'mobility'
    | 'care'
    | 'music'
    | 'study'
    | 'life'
    | 'other';

export type TrackingType =
    | 'weight_reps'
    | 'reps'
    | 'duration'
    | 'distance'
    | 'check'
    | 'count'
    | 'text';

export type RoutinePlanStatus = 'draft' | 'ready' | 'archived';

export type RoutineSessionStatus = 'in_progress' | 'completed' | 'aborted';

export type RoutineSessionStepStatus = 'pending' | 'completed' | 'skipped';

export type VideoStatus = 'pending' | 'ready';

export type ActivityLogEventType =
    | 'matrix_item_completed'
    | 'matrix_item_reopened'
    | 'routine_session_completed';

export type MetricValueType = 'decimal' | 'integer' | 'scale_1_5';

export type Video = {
    id: string;
    title: string;
    description: string | null;
    status: VideoStatus;
    mime_type: string | null;
    size_bytes: number | null;
    duration_seconds: number | null;
    life_area_id: string | null;
    created_at: string | null;
};

export type RoutineItem = {
    id: string;
    name: string;
    category: RoutineItemCategory;
    tracking_type: TrackingType;
    default_load_unit: string | null;
    default_amount_unit: string | null;
    note: string | null;
    is_active: boolean;
    life_area_id: string | null;
    videos_count?: number;
    created_at: string | null;
};

export type RoutineStep = {
    id: string;
    routine_id: string;
    routine_item_id: string;
    video_id: string | null;
    purpose: StepPurpose | null;
    sort_order: number;
    target_blocks: number | null;
    target_load: string | null;
    load_unit: string | null;
    target_amount: string | null;
    amount_unit: string | null;
    rest_seconds: number | null;
    note: string | null;
    routine_item?: RoutineItem;
    video?: Video;
};

export type Routine = {
    id: string;
    name: string;
    description: string | null;
    is_active: boolean;
    sort_order: number;
    life_area_id: string | null;
    steps_count?: number;
    created_at: string | null;
};

export type RoutineEditor = Routine & {
    life_area?: LifeArea;
    steps: RoutineStep[];
};

export type RoutinePlanStep = {
    id: string;
    routine_plan_id: string;
    routine_item_id: string;
    video_id: string | null;
    purpose: StepPurpose | null;
    sort_order: number;
    target_blocks: number | null;
    target_load: string | null;
    load_unit: string | null;
    target_amount: string | null;
    amount_unit: string | null;
    rest_seconds: number | null;
    note: string | null;
    routine_item?: RoutineItem;
    video?: Video;
};

export type RoutineBlockLog = {
    id: string;
    routine_session_step_id: string;
    block_number: number;
    load_value: string | null;
    load_unit: string | null;
    amount_value: string | null;
    amount_unit: string | null;
    memo: string | null;
};

export type RoutineSessionStep = {
    id: string;
    routine_session_id: string;
    routine_item_id: string;
    item_name: string;
    video_id: string | null;
    purpose: StepPurpose | null;
    sort_order: number;
    target_blocks: number | null;
    target_load: string | null;
    load_unit: string | null;
    target_amount: string | null;
    amount_unit: string | null;
    rest_seconds: number | null;
    status: RoutineSessionStepStatus;
    actual_duration_seconds: number | null;
    memo: string | null;
    routine_item?: RoutineItem;
    video?: Video;
    block_logs?: RoutineBlockLog[];
};

export type RoutineSession = {
    id: string;
    routine_plan_id: string;
    status: RoutineSessionStatus;
    started_at: string;
    finished_at: string | null;
    note: string | null;
    routine_plan?: RoutinePlan;
    steps?: RoutineSessionStep[];
};

export type RoutinePlan = {
    id: string;
    title: string;
    scheduled_on: string;
    status: RoutinePlanStatus;
    note: string | null;
    life_area_id: string | null;
    routine_id: string | null;
    life_area?: LifeArea;
    steps?: RoutinePlanStep[];
    sessions?: RoutineSession[];
    created_at: string | null;
};

export type TodayRoutines = {
    date: string;
    plans: RoutinePlan[];
};

export type Metric = {
    id: string;
    key: string;
    label: string;
    unit: string;
    value_type: MetricValueType;
    sort_order: number;
};

export type MetricRecord = {
    id: string;
    metric_id: string;
    life_area_id: string | null;
    recorded_on: string;
    value: string;
    note: string | null;
    metric?: Metric;
    life_area?: LifeArea;
};

export type DailyMetricEntry = {
    metric: Metric;
    record: MetricRecord | null;
};

export type ChartPoint = {
    date: string;
    value: string;
};

export type NutritionTotals = {
    kcal: number;
    protein_g: number;
    fat_g: number;
    carb_g: number;
};

export type MealEntry = {
    id: string;
    food_item_id: string | null;
    eaten_on: string;
    meal_type: 'breakfast' | 'lunch' | 'dinner' | 'snack';
    meal_type_label: string;
    name: string;
    quantity: string;
    kcal: string;
    protein_g: string;
    fat_g: string;
    carb_g: string;
    note: string | null;
};

export type MealSection = {
    meal_type: 'breakfast' | 'lunch' | 'dinner' | 'snack';
    label: string;
    entries: MealEntry[];
    subtotal: NutritionTotals;
};

export type NutritionGoal = {
    id: string;
    kcal: string;
    protein_g: string;
    fat_g: string;
    carb_g: string;
};

export type NutritionChartPoint = {
    date: string;
    kcal: number;
    protein_g: number;
    fat_g: number;
    carb_g: number;
};

export type FoodItem = {
    id: string;
    name: string;
    serving_label: string;
    kcal: string;
    protein_g: string;
    fat_g: string;
    carb_g: string;
    updated_at?: string | null;
};

export type ActivityLogSubjectSummary =
    | {
          type: 'matrix_cell_item';
          title: string;
      }
    | {
          type: 'routine_session';
          plan_title?: string;
          status?: RoutineSessionStatus;
      };

export type ActivityLog = {
    id: string;
    event_type: ActivityLogEventType;
    occurred_at: string;
    subject_type: string | null;
    subject_id: string | null;
    subject_summary: ActivityLogSubjectSummary | null;
};

export type Paginated<T> = {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
};

export type StepDurationInput = {
    target_blocks?: number | null;
    target_amount?: string | null;
    amount_unit?: string | null;
    rest_seconds?: number | null;
    tracking_type?: TrackingType | null;
};
