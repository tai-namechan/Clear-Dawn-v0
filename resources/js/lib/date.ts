/**
 * ローカルタイムゾーン基準の日付キー（YYYY-MM-DD）ユーティリティ。
 * toISOString() は UTC 変換するため JST では日付がずれる — 使用禁止。
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
