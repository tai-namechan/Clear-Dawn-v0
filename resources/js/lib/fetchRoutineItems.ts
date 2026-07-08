import type { RoutineItem } from '@/types/routine';

type InertiaPageResponse = {
    props?: {
        routineItems?: RoutineItem[];
    };
};

function getInertiaVersion(): string | undefined {
    const app = document.getElementById('app');

    if (!app?.dataset.page) {
        return undefined;
    }

    try {
        return JSON.parse(app.dataset.page).version as string;
    } catch {
        return undefined;
    }
}

/**
 * Inertia ページ props から実施項目一覧を取得する（部分リロード用）。
 */
export async function fetchRoutineItemsFromPage(): Promise<RoutineItem[]> {
    const version = getInertiaVersion();
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Inertia': 'true',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (version) {
        headers['X-Inertia-Version'] = version;
    }

    const response = await fetch('/routine-items', {
        headers,
        credentials: 'same-origin',
    });

    if (!response.ok) {
        return [];
    }

    const data = (await response.json()) as InertiaPageResponse;

    return data.props?.routineItems ?? [];
}
