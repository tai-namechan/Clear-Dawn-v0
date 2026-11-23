/**
 * Share of energy from protein / fat / carbohydrate (4 / 9 / 4 kcal per g),
 * as whole percentages. All zero when nothing was eaten.
 *
 * @param {{ protein_g: number | string, fat_g: number | string, carb_g: number | string }} totals
 * @returns {{ p: number, f: number, c: number }}
 */
export function pfcEnergyShare(totals) {
    const protein = (Number(totals.protein_g) || 0) * 4;
    const fat = (Number(totals.fat_g) || 0) * 9;
    const carb = (Number(totals.carb_g) || 0) * 4;
    const total = protein + fat + carb;

    if (total <= 0) {
        return { p: 0, f: 0, c: 0 };
    }

    return {
        p: Math.round((protein / total) * 100),
        f: Math.round((fat / total) * 100),
        c: Math.round((carb / total) * 100),
    };
}

/**
 * Intake as a share of a nutrient goal, capped at 100 for progress bars.
 * Returns null when there is no usable goal (never invent a target).
 *
 * @param {number | string} intake
 * @param {number | string | null | undefined} goal
 * @returns {number | null}
 */
export function nutrientProgress(intake, goal) {
    const target = Number(goal);

    if (!Number.isFinite(target) || target <= 0) {
        return null;
    }

    const value = Number(intake) || 0;

    return Math.max(0, Math.min(100, Math.round((value / target) * 100)));
}
