import type {
    ActivityLogEventType,
    ExerciseCategory,
    StepDurationInput,
    StepPurpose,
    TrackingType,
    TrainingPlanStatus,
    TrainingRunStatus,
    TrainingRunStepStatus,
    VideoStatus,
} from '@/types/training';

export const WORK_SECONDS_PER_SET = 30;

export const stepPurposeLabels: Record<StepPurpose, string> = {
    prep: '準備',
    movement: '動作',
    power: 'パワー',
    strength: '筋力',
    care: 'ケア',
    skill: 'スキル',
    other: 'その他',
};

export const exerciseCategoryLabels: Record<ExerciseCategory, string> = {
    strength: '筋力',
    baseball: '野球',
    mobility: 'モビリティ',
    care: 'ケア',
    music: '音楽',
    other: 'その他',
};

export const trackingTypeLabels: Record<TrackingType, string> = {
    weight_reps: '重量×回数',
    reps: '回数',
    duration: '時間',
    distance: '距離',
    check: 'チェック',
};

export const trainingPlanStatusLabels: Record<TrainingPlanStatus, string> = {
    draft: '下書き',
    ready: '準備完了',
    archived: 'アーカイブ',
};

export const trainingRunStatusLabels: Record<TrainingRunStatus, string> = {
    in_progress: '実行中',
    completed: '完了',
    aborted: '中断',
};

export const trainingRunStepStatusLabels: Record<
    TrainingRunStepStatus,
    string
> = {
    pending: '未完了',
    completed: '完了',
    skipped: 'スキップ',
};

export const videoStatusLabels: Record<VideoStatus, string> = {
    pending: '処理中',
    ready: '利用可能',
};

export const activityLogEventTypeLabels: Record<ActivityLogEventType, string> =
    {
        matrix_item_completed: 'マトリクス完了',
        matrix_item_reopened: 'マトリクス再開',
        training_run_completed: 'トレーニング完了',
    };

/** 種目カテゴリから purpose を推定（ステップに purpose 未設定時） */
export const categoryDefaultPurpose: Record<ExerciseCategory, StepPurpose> = {
    strength: 'strength',
    baseball: 'skill',
    mobility: 'care',
    care: 'care',
    music: 'skill',
    other: 'other',
};

export function resolveStepPurpose(
    purpose: StepPurpose | null,
    category?: ExerciseCategory | null,
): StepPurpose {
    if (purpose !== null) {
        return purpose;
    }

    if (category !== null && category !== undefined) {
        return categoryDefaultPurpose[category];
    }

    return 'other';
}

/**
 * ステップの想定所要時間（秒）を見積もる。
 * 作業時間 = セット数 × (target_duration_seconds ?? WORK_SECONDS_PER_SET)
 * 休憩 = (セット数 - 1) × rest_seconds
 */
export function estimateStepDurationSeconds(step: StepDurationInput): number {
    const sets = Math.max(step.target_sets ?? 1, 1);
    const rest = step.rest_seconds ?? 0;
    const workPerSet = step.target_duration_seconds ?? WORK_SECONDS_PER_SET;

    const workTotal = sets * workPerSet;
    const restTotal = sets > 1 ? (sets - 1) * rest : 0;

    return workTotal + restTotal;
}

export function formatDurationSeconds(totalSeconds: number): string {
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    if (minutes === 0) {
        return `${seconds}秒`;
    }

    if (seconds === 0) {
        return `${minutes}分`;
    }

    return `${minutes}分${seconds}秒`;
}

export function formatVideoDuration(seconds: number | null): string {
    if (seconds === null || seconds <= 0) {
        return '--:--';
    }

    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;

    return `${mins}:${secs.toString().padStart(2, '0')}`;
}
