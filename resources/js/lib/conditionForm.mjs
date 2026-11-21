/**
 * Stored metric values come back as decimal strings ("2.00", "62.60").
 * The form compares and shows them as plain numbers ("2", "62.6"), so the
 * 1–5 buttons light up and the inputs do not show trailing zeros.
 *
 * @param {string | number | null | undefined} raw
 * @returns {string}
 */
export function toFormValue(raw) {
    if (raw === null || raw === undefined || raw === '') {
        return '';
    }

    const value = Number(raw);

    return Number.isFinite(value) ? String(value) : String(raw);
}
