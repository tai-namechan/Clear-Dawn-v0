/**
 * How far a plan has progressed, for the bar on each session card.
 * Uses the latest session's steps when a session exists (completed or
 * skipped count as handled), otherwise the plan's own step count.
 *
 * @param {{ steps?: unknown[], sessions?: { started_at?: string, steps?: { status: string }[] }[] }} plan
 * @returns {{ done: number, total: number, percent: number }}
 */
export function planStepProgress(plan) {
    const sessions = [...(plan.sessions ?? [])].sort((a, b) =>
        String(b.started_at ?? '').localeCompare(String(a.started_at ?? '')),
    );
    const sessionSteps = sessions[0]?.steps;

    if (sessionSteps && sessionSteps.length > 0) {
        const done = sessionSteps.filter(
            (step) => step.status === 'completed' || step.status === 'skipped',
        ).length;

        return {
            done,
            total: sessionSteps.length,
            percent: Math.round((done / sessionSteps.length) * 100),
        };
    }

    return { done: 0, total: plan.steps?.length ?? 0, percent: 0 };
}
