/**
 * Rough planned minutes from step blocks + rest (no dedicated duration column).
 *
 * Kept as plain JavaScript so the supported Node 20 test runner can execute the
 * same implementation used by the Vue application.
 */
export function estimatePlanMinutes(plan) {
    const steps = plan.steps ?? [];

    if (steps.length === 0) {
        return null;
    }

    let seconds = 0;

    for (const step of steps) {
        const blocks = Math.max(1, step.target_blocks ?? 1);
        seconds += blocks * 120;

        if (step.rest_seconds && blocks > 1) {
            seconds += step.rest_seconds * (blocks - 1);
        }
    }

    return Math.max(1, Math.round(seconds / 60));
}

export function displayDurationMinutes(plan) {
    return estimatePlanMinutes(plan);
}
