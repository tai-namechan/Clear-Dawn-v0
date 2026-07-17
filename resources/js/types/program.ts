export type GoalStatus = 'draft' | 'active' | 'achieved' | 'abandoned';

export interface GoalMetricSummary {
    id: string;
    goal_id: string;
    metric_id: string;
    metric?: {
        id: string;
        key: string;
        label: string;
        unit: string;
        is_advanced: boolean;
    };
    baseline_value: string | null;
    target_value: string | null;
    target_low: string | null;
    target_high: string | null;
    direction: 'increase' | 'decrease' | 'maintain' | null;
    note: string | null;
    sort_order: number;
}

export interface GoalChangeLogEntry {
    id: string;
    changes: Record<string, unknown>;
    reason: string | null;
    created_at: string;
}

export interface GoalSummary {
    id: string;
    parent_goal_id: string | null;
    matrix_cell_id: string | null;
    name: string;
    why: string | null;
    priority: number;
    status: GoalStatus;
    deadline: string | null;
    sort_order: number;
    parent?: { id: string; name: string } | null;
    matrix_cell?: { id: string; life_area: string | null } | null;
    goal_metrics?: GoalMetricSummary[];
    children?: GoalSummary[];
    programs?: { id: string; name: string; status: string }[];
    change_logs?: GoalChangeLogEntry[];
}

export interface MetricOption {
    id: string;
    key: string;
    label: string;
    unit: string;
}

export interface ProgramSummary {
    id: string;
    name: string;
    purpose: string | null;
    status: string;
    goal?: { id: string; name: string } | null;
    active_version: {
        id: string;
        version_number: number;
        starts_on: string;
        ends_on: string;
        week_count: number;
        day_count: number;
        current_week_number: number | null;
    } | null;
}

export interface ProgramStepItemDetail {
    id: string;
    name: string;
    sets: number | null;
    reps: number | null;
    amount_value: string | null;
    amount_unit: string | null;
    fixed_load: string | null;
    load_unit: string | null;
    percent_of_reference: string | null;
    reference_lift: string | null;
    rpe_target: string | null;
    required_level: string;
    progression_mode: string;
    cues: string | null;
    abort_condition: string | null;
    completion_condition: string | null;
    note: string | null;
}

export interface ProgramDayStepDetail {
    id: string;
    name: string;
    step_kind: string;
    required_level: string;
    choice_option_id: string | null;
    estimated_minutes: number | null;
    note: string | null;
    items: ProgramStepItemDetail[];
}

export interface ProgramDayTemplateDetail {
    id: string;
    code: string;
    name: string;
    priority_tier: string;
    assignment_mode: string;
    fixed_weekday: number | null;
    estimated_minutes_min: number | null;
    estimated_minutes_max: number | null;
    is_optional: boolean;
    note: string | null;
    choice_group: {
        id: string;
        name: string;
        selection_hint: string | null;
        options: {
            id: string;
            label: string;
            description: string | null;
            estimated_minutes: number | null;
        }[];
    } | null;
    steps: ProgramDayStepDetail[];
}

export interface ProgramDetail {
    id: string;
    name: string;
    purpose: string | null;
    design_philosophy: string | null;
    status: string;
    goal: { id: string; name: string } | null;
    versions: {
        id: string;
        version_number: number;
        status: string;
        starts_on: string;
        ends_on: string;
        change_summary: string | null;
        change_reason: string | null;
    }[];
    active_version: {
        id: string;
        version_number: number;
        starts_on: string;
        ends_on: string;
        phases: {
            id: string;
            name: string;
            intent: string;
            week_numbers: number[];
            progression_conditions: string | null;
        }[];
        day_templates: ProgramDayTemplateDetail[];
        constraints: {
            id: string;
            key: string;
            kind: string;
            description: string;
        }[];
        metric_targets: {
            id: string;
            metric_label: string;
            metric_unit: string;
            target_value: string | null;
            target_low: string | null;
            target_high: string | null;
            note: string | null;
        }[];
        attachments: {
            id: string;
            title: string;
            mime_type: string | null;
            byte_size: number | null;
        }[];
    } | null;
}

export interface RoadmapPrescription {
    id: string;
    item_name: string;
    day_code: string;
    percent_of_reference: string | null;
    display_load: number | null;
    load_unit: string | null;
    sets: number | null;
    reps: number | null;
    rpe_target: string | null;
    is_test: boolean;
    intent: string | null;
    note: string | null;
}

export interface RoadmapData {
    version: {
        id: string;
        version_number: number;
        starts_on: string;
        ends_on: string;
        current_week_number: number | null;
    };
    phases: {
        id: string;
        name: string;
        intent: string;
        week_numbers: number[];
        progression_conditions: string | null;
    }[];
    weeks: {
        id: string;
        week_number: number;
        starts_on: string;
        intent: string | null;
        prescriptions: RoadmapPrescription[];
    }[];
    day_templates: {
        id: string;
        code: string;
        name: string;
        priority_tier: string;
        assignment_mode: string;
        fixed_weekday: number | null;
        estimated_minutes_min: number | null;
        estimated_minutes_max: number | null;
        is_optional: boolean;
        step_names: string[];
    }[];
}
