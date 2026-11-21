export function planStepProgress(plan: {
    steps?: unknown[];
    sessions?: { started_at?: string; steps?: { status: string }[] }[];
}): { done: number; total: number; percent: number };
