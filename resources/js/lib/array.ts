/**
 * Inertia / API から渡る配列 props がオブジェクトになるケースを吸収する。
 */
export function ensureArray<T>(
    value: readonly T[] | T[] | Record<string, T> | null | undefined,
): T[] {
    if (Array.isArray(value)) {
        return [...value];
    }

    if (value == null) {
        return [];
    }

    return Object.values(value);
}
