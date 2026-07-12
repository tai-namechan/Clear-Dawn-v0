/**
 * Join analysis gaps with AI suggestions by gap_key.
 * Display authority is always analysisGaps (order preserved).
 * Suggestions without a matching analysis key are dropped.
 *
 * @param {Array<{key: string, start: string, end: string, minutes: number}>} analysisGaps
 * @param {Array<{gap_key: string, suggestion?: string, start?: string, end?: string, minutes?: number}>} suggestions
 * @returns {Array<{gap_key: string, suggestion: string, start: string, end: string, minutes: number}>}
 */
export function joinGapsWithSuggestions(analysisGaps, suggestions) {
    const gaps = Array.isArray(analysisGaps) ? analysisGaps : [];
    const rows = Array.isArray(suggestions) ? suggestions : [];
    /** @type {Map<string, string>} */
    const byKey = new Map();

    for (const row of rows) {
        if (!row || typeof row !== 'object') {
            continue;
        }

        const key = row.gap_key;

        if (typeof key !== 'string' || key === '') {
            continue;
        }

        if (byKey.has(key)) {
            continue;
        }

        const suggestion =
            typeof row.suggestion === 'string' ? row.suggestion : '';
        byKey.set(key, suggestion);
    }

    return gaps.map((gap) => ({
        gap_key: gap.key,
        suggestion: byKey.get(gap.key) ?? '',
        start: gap.start,
        end: gap.end,
        minutes: gap.minutes,
    }));
}
