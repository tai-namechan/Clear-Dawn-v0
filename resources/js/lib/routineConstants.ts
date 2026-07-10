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

/** ステップ追加モーダル用の記録形式タブ表示 */
export const trackingTypeTabLabels: Record<TrackingType, string> = {
    reps: '回数',
    duration: '時間',
    distance: '距離',
    weight_reps: '重量×回数',
    check: 'チェック',
    count: 'カウント',
    text: 'カスタム',
};

export const defaultLoadUnit = 'kg';

export const defaultAmountUnitForTracking: Record<TrackingType, string> = {
    weight_reps: '回',
    reps: '回',
    duration: '秒',
    distance: 'km',
    check: '',
    count: '回',
    text: '',
};

/** 単位セレクトの「その他」値（自由入力に切替） */
export const UNIT_PRESET_OTHER = '__other__';

export const amountUnitPresets: string[] = [
    '回',
    'ページ',
    '問',
    '小節',
    'BPM',
    'レベル',
    '点',
    '秒',
    '分',
    'km',
    'm',
];

export const loadUnitPresets: string[] = ['kg', 'lb'];

export const itemNamePlaceholders = [
    '例: WGS',
    '例: カノン Aパート',
    '例: AWS IAM章',
    '例: スクワット',
] as const;

export const stepTitlePlaceholders = [
    '例: ゆっくり確認',
    '例: 権限まわりを復習',
    '例: 通常スクワット',
] as const;

export const videoPlaceholders = [
    '例: 通常スクワット動画',
] as const;

/**
 * ステップ表示名: title があればそれ、なければ実施項目名。
 */
export function resolveStepDisplayName(
    title: string | null | undefined,
    itemName: string | null | undefined,
): string {
    const trimmed = title?.trim() ?? '';

    if (trimmed !== '') {
        return trimmed;
    }

    return itemName?.trim() || '—';
}

/**
 * 保存済み単位がプリセットに含まれるか判定し、セレクト用の値を返す。
 */
export function unitSelectValue(
    unit: string | null | undefined,
    presets: string[],
): string {
    if (!unit) {
        return presets[0] ?? UNIT_PRESET_OTHER;
    }

    return presets.includes(unit) ? unit : UNIT_PRESET_OTHER;
}

export const trackingTypeOptions: TrackingType[] = [
    'reps',
    'duration',
    'distance',
    'weight_reps',
    'count',
    'check',
    'text',
];

export const stepPurposeOptions: StepPurpose[] = [
    'prep',
    'movement',
    'power',
    'strength',
    'care',
    'practice',
    'study',
    'review',
    'other',
];

export const routineItemCategoryOptions: RoutineItemCategory[] = [
    'strength',
    'baseball',
    'mobility',
    'care',
    'music',
    'study',
    'life',
    'other',
];

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
    load: string | number | null | undefined,
    unit: string | null | undefined,
): string | null {
    if (load === null || load === undefined || load === '') {
        return null;
    }

    const text = String(load);

    return unit ? `${text}${unit}` : text;
}

export function formatAmountTarget(
    amount: string | number | null | undefined,
    unit: string | null | undefined,
): string | null {
    if (amount === null || amount === undefined || amount === '') {
        return null;
    }

    const text = String(amount);

    return unit ? `${text}${unit}` : text;
}

export function formatStepTarget(step: {
    target_blocks?: number | null;
    target_load?: string | number | null;
    load_unit?: string | null;
    target_amount?: string | number | null;
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
    load_value?: string | number | null;
    load_unit?: string | null;
    amount_value?: string | number | null;
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
