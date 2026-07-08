/**
 * ローカルタイムゾーン基準の日付キー（YYYY-MM-DD）ユーティリティ。
 * toISOString() は UTC 変換するため日付ナビでは使わない。
 */

export function toDateKey(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

export function parseDateKey(dateKey: string): Date {
    return new Date(`${dateKey}T00:00:00`);
}

export function todayKey(): string {
    return toDateKey(new Date());
}

export function shiftDateKey(dateKey: string, days: number): string {
    const date = parseDateKey(dateKey);
    date.setDate(date.getDate() + days);

    return toDateKey(date);
}

export function isTodayKey(dateKey: string): boolean {
    return dateKey === todayKey();
}

export function formatDateKeyJa(dateKey: string): string {
    return parseDateKey(dateKey).toLocaleDateString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        weekday: 'short',
    });
}
