/**
 * Helpers for the 7-day condition trend cards on the performance screen.
 */

/**
 * Mean of the recorded values (missing days are skipped, never treated as 0).
 *
 * @param {{ value: string | number }[]} points
 * @returns {number | null}
 */
export function trendAverage(points) {
    const values = points
        .map((point) => Number(point.value))
        .filter((value) => Number.isFinite(value));

    if (values.length === 0) {
        return null;
    }

    return values.reduce((sum, value) => sum + value, 0) / values.length;
}

/**
 * Compact x-axis labels like the mockup: the first day as "M/D", then "D".
 *
 * @param {string[]} dates ISO dates (YYYY-MM-DD)
 * @returns {string[]}
 */
export function trendAxisLabels(dates) {
    return dates.map((date, index) => {
        const [, month, day] = date.split('-').map(Number);

        return index === 0 ? `${month}/${day}` : String(day);
    });
}
