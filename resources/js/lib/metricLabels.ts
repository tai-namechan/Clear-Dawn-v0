/** メトリクス key → 日本語ラベル（マスタ欠損時のフロントフォールバック） */
export const METRIC_LABELS: Record<string, string> = {
    weight: '体重',
    sleep_minutes: '睡眠時間',
    pitch_speed_max: '最高球速',
    pitch_count: '投球数',
    pain_level: '痛みレベル',
    fatigue_level: '疲労レベル',
};

export function metricLabel(key: string, fallback?: string | null): string {
    return fallback || METRIC_LABELS[key] || key;
}

export function formatSleepMinutes(value: number): string {
    const hours = Math.floor(value / 60);
    const minutes = Math.round(value % 60);

    if (hours <= 0) {
        return `${minutes}分`;
    }

    if (minutes <= 0) {
        return `${hours}時間`;
    }

    return `${hours}時間${minutes}分`;
}

export function formatSleepDelta(diffMinutes: number): string {
    const sign = diffMinutes > 0 ? '▲' : '▼';
    const abs = Math.abs(Math.round(diffMinutes));
    const hours = Math.floor(abs / 60);
    const minutes = abs % 60;

    if (hours <= 0) {
        return `${sign} ${minutes}分`;
    }

    if (minutes <= 0) {
        return `${sign} ${hours}時間`;
    }

    return `${sign} ${hours}時間${minutes}分`;
}
