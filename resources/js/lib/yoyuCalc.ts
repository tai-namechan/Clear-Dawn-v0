export type CalEvent = {
    id: string;
    title: string;
    start: string;
    end: string;
    place: string;
    travel_min: number | null;
    color: string;
};

export type YoyuTaskLike = {
    status: string;
    estimate_minutes: number;
};

export const PREP_MIN = 10;
export const BUFFER_MIN = 5;

export type TubStatus = 'yoyu' | 'tapu' | 'over';

export const TUB_LABEL: Record<TubStatus, string> = {
    yoyu: '余裕あり',
    tapu: 'たっぷたぷ',
    over: 'あふれています',
};

export function fmtTime(iso: string): string {
    const d = new Date(iso);

    return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}

export function departInfo(event: CalEvent, nowMs: number) {
    const start = new Date(event.start).getTime();
    const lead =
        (event.travel_min != null && event.travel_min > 0
            ? event.travel_min + PREP_MIN + BUFFER_MIN
            : 0) * 60000;
    const depart = start - lead;
    const min = Math.round((depart - nowMs) / 60000);

    return {
        depart,
        min,
        travel: event.travel_min != null && event.travel_min > 0,
    };
}

export function yoyuCalc(
    nowMs: number,
    calendar: CalEvent[],
    doneEventIds: string[],
    tasks: YoyuTaskLike[],
): { level: number; status: TubStatus; busy: number; free: number } {
    const dayEnd = new Date();
    dayEnd.setHours(22, 0, 0, 0);
    const remainMin = Math.max((dayEnd.getTime() - nowMs) / 60000, 30);

    let busy = 0;

    for (const event of calendar) {
        const start = new Date(event.start).getTime();
        const end = new Date(event.end).getTime();

        if (doneEventIds.includes(event.id) || end <= nowMs) {
            continue;
        }

        busy += (end - Math.max(start, nowMs)) / 60000;

        if (start > nowMs && event.travel_min != null && event.travel_min > 0) {
            busy += event.travel_min + PREP_MIN + BUFFER_MIN;
        }
    }

    for (const task of tasks) {
        if (['planned', 'doing', 'inbox'].includes(task.status)) {
            busy += task.estimate_minutes || 30;
        }
    }

    const density = Math.min(busy / remainMin, 1.3);
    const level = Math.max(Math.min(0.3 + density * 0.85, 1.12), 0.22);
    const status: TubStatus =
        level > 1 ? 'over' : level >= 0.72 ? 'tapu' : 'yoyu';

    return {
        level,
        status,
        busy: Math.round(busy),
        free: Math.round(Math.max(remainMin - busy, 0)),
    };
}

export function formatMinutes(min: number): string {
    if (min >= 60) {
        const h = Math.floor(min / 60);
        const m = min % 60;

        return m ? `${h}時間${m}分` : `${h}時間`;
    }

    return `${min}分`;
}
