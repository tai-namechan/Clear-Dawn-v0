import type {
    ActivityLogEventType,
    RoutineItemCategory,
    RoutinePlanStatus,
    RoutineSessionStatus,
    RoutineSessionStepStatus,
    StepDurationInput,
    StepPurpose,
    TrackingType,
    VideoStatus,
} from '@/types/routine';

export const WORK_SECONDS_PER_BLOCK = 30;

export const stepPurposeLabels: Record<StepPurpose, string> = {
    prep: '準備',
    movement: '動作',
    power: 'パワー',
    strength: '筋力',
    care: 'ケア',
    practice: '練習',
    study: '学習',
    review: '復習',
    other: 'その他',
};

export const routineItemCategoryLabels: Record<RoutineItemCategory, string> = {
    strength: '筋力',
    baseball: '野球',
    mobility: 'モビリティ',
    care: 'ケア',
    music: '音楽',
    study: '学習',
    life: '生活',
    other: 'その他',
};

export const trackingTypeLabels: Record<TrackingType, string> = {
    weight_reps: '負荷×量',
    reps: '量（回数）',
    duration: '量（時間）',
    distance: '量（距離）',
    check: 'チェック',
    count: '量（カウント）',
    text: 'テキスト',
};

export const routinePlanStatusLabels: Record<RoutinePlanStatus, string> = {
    draft: '下書き',
    ready: '準備完了',
    archived: 'アーカイブ',
};

export const routineSessionStatusLabels: Record<RoutineSessionStatus, string> =
    {
        in_progress: '実行中',
        completed: '完了',
        aborted: '中断',
    };

export const routineSessionStepStatusLabels: Record<
    RoutineSessionStepStatus,
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
        routine_session_completed: 'ルーティン実行完了',
    };

/** 実施項目カテゴリから purpose を推定（ステップに purpose 未設定時） */
export const categoryDefaultPurpose: Record<RoutineItemCategory, StepPurpose> =
    {
        strength: 'strength',
        baseball: 'practice',
        mobility: 'care',
        care: 'care',
        music: 'practice',
        study: 'study',
        life: 'other',
        other: 'other',
    };

export function resolveStepPurpose(
    purpose: StepPurpose | null,
    category?: RoutineItemCategory | null,
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
 * 作業時間 = ブロック数 × (target_amount as seconds ?? WORK_SECONDS_PER_BLOCK)
 * 休憩 = (ブロック数 - 1) × rest_seconds
 */
export function estimateStepDurationSeconds(step: StepDurationInput): number {
    const blocks = Math.max(step.target_blocks ?? 1, 1);
    const rest = step.rest_seconds ?? 0;
    const workPerBlock =
        step.amount_unit === '秒' && step.target_amount
            ? Number(step.target_amount)
            : WORK_SECONDS_PER_BLOCK;

    const workTotal = blocks * workPerBlock;
    const restTotal = blocks > 1 ? (blocks - 1) * rest : 0;

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

export function formatLoadTarget(
    load: string | null,
    unit: string | null,
): string | null {
    if (!load) {
        return null;
    }

    return unit ? `${load}${unit}` : load;
}

export function formatAmountTarget(
    amount: string | null,
    unit: string | null,
): string | null {
    if (!amount) {
        return null;
    }

    return unit ? `${amount}${unit}` : amount;
}

export function formatStepTarget(step: {
    target_blocks?: number | null;
    target_load?: string | null;
    load_unit?: string | null;
    target_amount?: string | null;
    amount_unit?: string | null;
    routine_item?: { category?: RoutineItemCategory } | null;
}): string {
    const parts: string[] = [];
    const isStrength = step.routine_item?.category === 'strength';

    if (step.target_blocks) {
        parts.push(
            isStrength
                ? `${step.target_blocks}セット`
                : `${step.target_blocks}ブロック`,
        );
    }

    const load = formatLoadTarget(step.target_load, step.load_unit);
    if (load) {
        parts.push(load);
    }

    const amount = formatAmountTarget(step.target_amount, step.amount_unit);
    if (amount) {
        parts.push(amount);
    }

    return parts.join(' · ') || '—';
}

export function formatBlockLog(log: {
    load_value?: string | null;
    load_unit?: string | null;
    amount_value?: string | null;
    amount_unit?: string | null;
}): string {
    const parts: string[] = [];

    const load = formatLoadTarget(log.load_value, log.load_unit);
    if (load) {
        parts.push(load);
    }

    const amount = formatAmountTarget(log.amount_value, log.amount_unit);
    if (amount) {
        parts.push(amount);
    }

    return parts.join(' × ') || '—';
}
