export type KcalAchievement = {
    percent: number | null;
    ringPercent: number;
    difference: number | null;
    status: 'over' | 'remaining' | 'reached' | 'unset';
};

export function kcalAchievement(
    intakeKcal: number,
    goalKcal: number | string | null | undefined,
): KcalAchievement;
export function kcalAchievementBadge(
    achievement: KcalAchievement,
): string | null;
