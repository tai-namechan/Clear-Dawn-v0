/**
 * Format minor-unit money strings for display.
 * Never use Number() for arithmetic — BigInt / string grouping only.
 */

function normalizeMinor(amountMinor: string | null | undefined): string | null {
    if (amountMinor === null || amountMinor === undefined) {
        return null;
    }

    const trimmed = String(amountMinor).trim();

    if (trimmed === '' || !/^-?\d+$/.test(trimmed)) {
        return null;
    }

    if (trimmed === '-0') {
        return '0';
    }

    return trimmed;
}

function groupDigits(absoluteDigits: string): string {
    const digits = absoluteDigits.replace(/^0+(?=\d)/, '') || '0';
    const parts: string[] = [];

    for (let i = digits.length; i > 0; i -= 3) {
        const start = Math.max(0, i - 3);
        parts.unshift(digits.slice(start, i));
    }

    return parts.join(',');
}

/**
 * Format a minor-unit amount as Japanese yen (e.g. "1200" → "¥1,200").
 */
export function formatYen(amountMinor: string | null | undefined): string {
    const normalized = normalizeMinor(amountMinor);

    if (normalized === null) {
        return '—';
    }

    const negative = normalized.startsWith('-');
    const absolute = negative ? normalized.slice(1) : normalized;
    const grouped = groupDigits(absolute);

    return negative ? `−¥${grouped}` : `¥${grouped}`;
}

/**
 * Format with an explicit leading + for non-negative values.
 */
export function formatSignedYen(amountMinor: string): string {
    const normalized = normalizeMinor(amountMinor);

    if (normalized === null) {
        return '—';
    }

    if (normalized.startsWith('-')) {
        return formatYen(normalized);
    }

    if (normalized === '0') {
        return '¥0';
    }

    return `+${formatYen(normalized)}`;
}

export function isNegativeMinor(
    amountMinor: string | null | undefined,
): boolean {
    const normalized = normalizeMinor(amountMinor);

    if (normalized === null) {
        return false;
    }

    return BigInt(normalized) < 0n;
}

export function isPositiveMinor(
    amountMinor: string | null | undefined,
): boolean {
    const normalized = normalizeMinor(amountMinor);

    if (normalized === null) {
        return false;
    }

    return BigInt(normalized) > 0n;
}

export function minorToDisplayString(
    value: string | number | null | undefined,
): string | null {
    if (value === null || value === undefined) {
        return null;
    }

    return String(value);
}
