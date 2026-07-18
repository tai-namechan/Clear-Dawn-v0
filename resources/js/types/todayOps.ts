export type TodayOpsCheckin = {
    id: string;
    checked_on: string;
    sleep_quality: number | null;
    fatigue: number | null;
    muscle_soreness: number | null;
    stress: number | null;
    mood: number | null;
    region_tension: Record<string, number> | null;
    readiness_self: number | null;
    note: string | null;
};

export type CheckinFormState = {
    sleep_quality: number;
    fatigue: number;
    muscle_soreness: number;
    stress: number;
    mood: number;
    readiness_self: number;
};

export type TodayOpsProgramContext = {
    plan_id: string;
    title: string;
    status: string;
    week_number: number | null;
    day_code: string | null;
    day_name: string | null;
    choice_option_id: string | null;
    needs_choice: boolean;
    choice_options: Array<{
        id: string;
        label: string;
        description: string | null;
        estimated_minutes: number | null;
    }>;
};

export type TodayOpsRecommendation = {
    id: string;
    title: string;
    rationale: string | null;
    goal_impact: string | null;
    scope: string;
    confidence: string | null;
    is_interrupt: boolean;
    status: string;
    missing_data: string[] | null;
    options: Array<{
        id: string;
        action_key: string;
        label: string;
        description: string | null;
    }>;
    decision: {
        action_key: string;
        reason: string | null;
    } | null;
};

export type TodayOpsNutrition = {
    profile: {
        name: string;
        kcal: string;
        protein_g: string;
        fat_g: string;
        carb_g: string;
    } | null;
    fallback_goal: {
        kcal: string;
        protein_g: string;
        fat_g: string;
        carb_g: string;
    } | null;
    intake?: {
        kcal: number;
        protein_g: number;
        fat_g: number;
        carb_g: number;
    };
};

export type TodayOps = {
    checkin: TodayOpsCheckin | null;
    program_context: TodayOpsProgramContext[];
    recommendations: TodayOpsRecommendation[];
    recent_symptoms: Array<{
        id: string;
        observed_on: string;
        body_region: string;
        symptom_kind: string;
        severity: number;
        is_new: boolean;
        note: string | null;
    }>;
    nutrition: TodayOpsNutrition;
};
