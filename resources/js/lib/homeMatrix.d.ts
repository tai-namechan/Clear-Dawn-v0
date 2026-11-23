export type AreaIconKey =
    'ball' | 'music' | 'study' | 'health' | 'work' | 'private' | 'default';

export function areaIconKey(areaName: string): AreaIconKey;
export function formatHomeDate(date: Date): string;
export function focusTabLabel(rowKey: string, fallback: string): string;
export function cellItemCount(
    rows: { key: string; cells: { items: unknown[] }[] }[],
    rowKey: string,
    areaIndex: number,
): number;
