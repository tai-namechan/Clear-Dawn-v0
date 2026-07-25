import type { StepPurpose } from '@/types/routine';

export type StepPurposeColorClasses = {
    chip: string;
    chart: string;
};

/** StepPurpose → チャートトークン系 Tailwind クラス（chip は塗りつぶし白文字） */
export const stepPurposeColorClasses: Record<
    StepPurpose,
    StepPurposeColorClasses
> = {
    prep: {
        chip: 'border-transparent bg-chart-1 text-white',
        chart: 'text-chart-1',
    },
    movement: {
        chip: 'border-transparent bg-chart-2 text-white',
        chart: 'text-chart-2',
    },
    power: {
        chip: 'border-transparent bg-chart-3 text-white',
        chart: 'text-chart-3',
    },
    strength: {
        chip: 'border-transparent bg-primary text-white',
        chart: 'text-chart-4',
    },
    care: {
        chip: 'border-transparent bg-chart-5 text-white',
        chart: 'text-chart-5',
    },
    practice: {
        chip: 'border-transparent bg-primary text-white',
        chart: 'text-primary',
    },
    study: {
        chip: 'border-transparent bg-chart-2 text-white',
        chart: 'text-chart-2',
    },
    review: {
        chip: 'border-transparent bg-chart-3 text-white',
        chart: 'text-chart-3',
    },
    other: {
        chip: 'border-transparent bg-cd-ink-muted text-white',
        chart: 'text-cd-ink-muted',
    },
};

export function purposeChipClasses(purpose: StepPurpose): string {
    return (
        stepPurposeColorClasses[purpose]?.chip ??
        stepPurposeColorClasses.other.chip
    );
}
