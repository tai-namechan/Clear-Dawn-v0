/**
 * Intake vs goal for the Clear Dawn performance / meal / session screens.
 *
 * Going over the goal is not treated as success: the ring is capped at 100%
 * while the real percentage is shown as text (design guidelines, 数字とカード).
 *
 * @param {number} intakeKcal
 * @param {number | string | null | undefined} goalKcal
 * @returns {{ percent: number | null, ringPercent: number, difference: number | null, status: 'over' | 'remaining' | 'reached' | 'unset' }}
 */
export function kcalAchievement(intakeKcal, goalKcal) {
    const goal = Number(goalKcal);
    const intake = Number(intakeKcal) || 0;

    if (!Number.isFinite(goal) || goal <= 0) {
        return {
            percent: null,
            ringPercent: 0,
            difference: null,
            status: 'unset',
        };
    }

    const percent = Math.round((intake / goal) * 100);
    const difference = Math.round(intake - goal);

    let status = 'reached';

    if (difference > 0) {
        status = 'over';
    } else if (difference < 0) {
        status = 'remaining';
    }

    return {
        percent,
        ringPercent: Math.max(0, Math.min(100, percent)),
        difference,
        status,
    };
}

/**
 * Badge text for the achievement card, e.g. "超過 +165 kcal" / "残り 320 kcal".
 *
 * @param {ReturnType<typeof kcalAchievement>} achievement
 * @returns {string | null}
 */
export function kcalAchievementBadge(achievement) {
    if (achievement.difference === null) {
        return null;
    }

    const amount = Math.abs(achievement.difference).toLocaleString('ja-JP');

    if (achievement.status === 'over') {
        return `超過 +${amount} kcal`;
    }

    if (achievement.status === 'remaining') {
        return `残り ${amount} kcal`;
    }

    return '目標ちょうど';
}
