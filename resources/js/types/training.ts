import type { LifeArea } from '@/types/matrix';

export type StepPurpose =
    | 'prep'
    | 'movement'
    | 'power'
    | 'strength'
    | 'care'
    | 'skill'
    | 'other';

export type ExerciseCategory =
    | 'strength'
    | 'baseball'
    | 'mobility'
    | 'care'
    | 'music'
    | 'other';

export type TrackingType =
    | 'weight_reps'
    | 'reps'
    | 'duration'
    | 'distance'
    | 'check';

export type TrainingPlanStatus = 'draft' | 'ready' | 'archived';

export type TrainingRunStatus = 'in_progress' | 'completed' | 'aborted';

export type TrainingRunStepStatus = 'pending' | 'completed' | 'skipped';

export type VideoStatus = 'pending' | 'ready';

export type ActivityLogEventType =
    | 'matrix_item_completed'
    | 'matrix_item_reopened'
    | 'training_run_completed';

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

export type Exercise = {
    id: string;
    name: string;
    category: ExerciseCategory;
    tracking_type: TrackingType;
    note: string | null;
    is_active: boolean;
    life_area_id: string | null;
    videos_count?: number;
    created_at: string | null;
};

export type RoutineStep = {
    id: string;
    routine_id: string;
    exercise_id: string;
    video_id: string | null;
    purpose: StepPurpose | null;
    sort_order: number;
    target_sets: number | null;
    target_reps: number | null;
    target_weight_kg: string | null;
    target_distance_m: string | null;
    target_duration_seconds: number | null;
    rest_seconds: number | null;
    note: string | null;
    exercise?: Exercise;
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

export type TrainingPlanStep = {
    id: string;
    training_plan_id: string;
    exercise_id: string;
    video_id: string | null;
    purpose: StepPurpose | null;
    sort_order: number;
    target_sets: number | null;
    target_reps: number | null;
    target_weight_kg: string | null;
    target_distance_m: string | null;
    target_duration_seconds: number | null;
    rest_seconds: number | null;
    note: string | null;
    exercise?: Exercise;
    video?: Video;
};

export type TrainingSetLog = {
    id: string;
    training_run_step_id: string;
    set_number: number;
    weight_kg: string | null;
    reps: number | null;
    distance_m: string | null;
    duration_seconds: number | null;
    memo: string | null;
};

export type TrainingRunStep = {
    id: string;
    training_run_id: string;
    exercise_id: string;
    exercise_name: string;
    video_id: string | null;
    purpose: StepPurpose | null;
    sort_order: number;
    target_sets: number | null;
    target_reps: number | null;
    target_weight_kg: string | null;
    target_distance_m: string | null;
    target_duration_seconds: number | null;
    rest_seconds: number | null;
    status: TrainingRunStepStatus;
    actual_duration_seconds: number | null;
    memo: string | null;
    exercise?: Exercise;
    video?: Video;
    set_logs?: TrainingSetLog[];
};

export type TrainingRun = {
    id: string;
    training_plan_id: string;
    status: TrainingRunStatus;
    started_at: string;
    finished_at: string | null;
    note: string | null;
    training_plan?: TrainingPlan;
    steps?: TrainingRunStep[];
};

export type TrainingPlan = {
    id: string;
    title: string;
    scheduled_on: string;
    status: TrainingPlanStatus;
    note: string | null;
    life_area_id: string | null;
    routine_id: string | null;
    life_area?: LifeArea;
    steps?: TrainingPlanStep[];
    runs?: TrainingRun[];
    created_at: string | null;
};

export type TodayTraining = {
    date: string;
    plans: TrainingPlan[];
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

export type ActivityLogSubjectSummary =
    | {
          type: 'matrix_cell_item';
          title: string;
      }
    | {
          type: 'training_run';
          plan_title?: string;
          status?: TrainingRunStatus;
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
    target_sets?: number | null;
    target_duration_seconds?: number | null;
    rest_seconds?: number | null;
    tracking_type?: TrackingType | null;
};
