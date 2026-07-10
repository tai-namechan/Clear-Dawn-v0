import type {
    RoutinePlan,
    RoutineSession,
    StepPurpose,
} from '@/types/routine';

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

/** Rough planned minutes from step blocks + rest (no dedicated duration column). */
export function estimatePlanMinutes(plan: RoutinePlan): number | null {
    const steps = plan.steps ?? [];

    if (steps.length === 0) {
        return null;
    }

    let seconds = 0;

    for (const step of steps) {
        const blocks = Math.max(1, step.target_blocks ?? 1);
        // ~2 min effort per block when no duration tracking
        seconds += blocks * 120;

        if (step.rest_seconds && blocks > 1) {
            seconds += step.rest_seconds * (blocks - 1);
        }
    }

    return Math.max(1, Math.round(seconds / 60));
}

export function sessionDurationMinutes(
    session: RoutineSession | null,
): number | null {
    if (!session?.started_at) {
        return null;
    }

    const start = Date.parse(session.started_at);
    const end = session.finished_at
        ? Date.parse(session.finished_at)
        : Date.now();

    if (Number.isNaN(start) || Number.isNaN(end) || end <= start) {
        return null;
    }

    return Math.max(1, Math.round((end - start) / 60_000));
}

export function displayDurationMinutes(plan: RoutinePlan): number | null {
    const session = latestSession(plan);
    const status = planRunStatus(plan);

    if (status === 'completed' || status === 'in_progress') {
        return sessionDurationMinutes(session) ?? estimatePlanMinutes(plan);
    }

    return estimatePlanMinutes(plan);
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
