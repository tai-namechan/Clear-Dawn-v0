import type { StepPurpose } from '@/types/training';

export type StepPurposeColorClasses = {
    chip: string;
    chart: string;
};

/** StepPurpose → チャートトークン系 Tailwind クラス */
export const stepPurposeColorClasses: Record<
    StepPurpose,
    StepPurposeColorClasses
> = {
    prep: {
        chip: 'border-chart-1/30 bg-chart-1/10 text-chart-1',
        chart: 'text-chart-1',
    },
    movement: {
        chip: 'border-chart-2/30 bg-chart-2/10 text-chart-2',
        chart: 'text-chart-2',
    },
    power: {
        chip: 'border-chart-3/30 bg-chart-3/10 text-chart-3',
        chart: 'text-chart-3',
    },
    strength: {
        chip: 'border-chart-4/30 bg-chart-4/10 text-chart-4',
        chart: 'text-chart-4',
    },
    care: {
        chip: 'border-chart-5/30 bg-chart-5/10 text-chart-5',
        chart: 'text-chart-5',
    },
    skill: {
        chip: 'border-primary/30 bg-primary/10 text-primary',
        chart: 'text-primary',
    },
    other: {
        chip: 'border-cd-line bg-muted text-cd-ink-muted',
        chart: 'text-cd-ink-muted',
    },
};

export function purposeChipClasses(purpose: StepPurpose): string {
    return stepPurposeColorClasses[purpose]?.chip
        ?? stepPurposeColorClasses.other.chip;
}
