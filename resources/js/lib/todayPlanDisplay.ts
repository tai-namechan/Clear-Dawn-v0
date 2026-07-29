import type {
    RoutineItemCategory,
    RoutinePlan,
    RoutineSession,
    StepPurpose,
} from '@/types/routine';
export {
    displayDurationMinutes,
    estimatePlanMinutes,
} from './todayPlanDuration.mjs';

export type TodayPlanRunStatus = 'completed' | 'in_progress' | 'not_started';

export function latestSession(plan: RoutinePlan): RoutineSession | null {
    return plan.sessions?.[0] ?? null;
}

export function planRunStatus(plan: RoutinePlan): TodayPlanRunStatus {
    const session = latestSession(plan);

    if (session?.status === 'completed') {
        return 'completed';
    }

    if (session?.status === 'in_progress') {
        return 'in_progress';
    }

    return 'not_started';
}

export function formatMinutesJa(totalMinutes: number): string {
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;

    if (hours > 0 && minutes > 0) {
        return `${hours}時間 ${minutes}分`;
    }

    if (hours > 0) {
        return `${hours}時間`;
    }

    return `${minutes} 分`;
}

export function formatClockRange(
    startedAt: string | null | undefined,
    finishedAt: string | null | undefined,
): string | null {
    if (!startedAt) {
        return null;
    }

    const start = new Date(startedAt);

    if (Number.isNaN(start.getTime())) {
        return null;
    }

    const startLabel = start.toLocaleTimeString('ja-JP', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });

    if (!finishedAt) {
        return `${startLabel} -`;
    }

    const end = new Date(finishedAt);

    if (Number.isNaN(end.getTime())) {
        return startLabel;
    }

    const endLabel = end.toLocaleTimeString('ja-JP', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });

    return `${startLabel} - ${endLabel}`;
}

export function planDescription(plan: RoutinePlan): string {
    if (plan.note?.trim()) {
        return plan.note.trim();
    }

    const names = (plan.steps ?? [])
        .map((step) => step.display_name)
        .filter(Boolean);

    if (names.length === 0) {
        return 'ステップ未設定';
    }

    if (names.length <= 3) {
        return names.join('・');
    }

    return `${names.slice(0, 3).join('・')} ほか`;
}

export function primaryStepPurpose(plan: RoutinePlan): StepPurpose | null {
    return plan.steps?.find((step) => step.purpose)?.purpose ?? null;
}

/**
 * プランの性格を表すカテゴリ（最も多いステップのカテゴリ）。
 *
 * 先頭ステップだけを見ると、どのプランも準備運動から始まるため同じ判定になる。
 * 同数のときは先に現れたカテゴリを採る。`other` は内容を表さないので、
 * 他のカテゴリが1つでもあればそちらを優先する。
 */
export function dominantItemCategory(
    plan: RoutinePlan,
): RoutineItemCategory | null {
    const counts = new Map<RoutineItemCategory, number>();

    for (const step of plan.steps ?? []) {
        const category = step.routine_item?.category;

        if (!category) {
            continue;
        }

        counts.set(category, (counts.get(category) ?? 0) + 1);
    }

    const meaningful = [...counts].filter(([category]) => category !== 'other');
    const ranked = meaningful.length > 0 ? meaningful : [...counts];

    let dominant: RoutineItemCategory | null = null;
    let dominantCount = 0;

    for (const [category, count] of ranked) {
        if (count > dominantCount) {
            dominant = category;
            dominantCount = count;
        }
    }

    return dominant;
}
