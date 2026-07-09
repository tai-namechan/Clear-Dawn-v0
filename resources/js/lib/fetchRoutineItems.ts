import { apiFetch } from '@/lib/apiFetch';
import type { RoutineItem } from '@/types/routine';

/**
 * 実施項目一覧を JSON API で取得する（Inertia 409 を避ける）。
 */
export async function fetchRoutineItemsFromPage(): Promise<RoutineItem[]> {
    const result = await apiFetch<{ routine_items: RoutineItem[] }>(
        '/routine-items',
        {
            headers: {
                Accept: 'application/json',
            },
        },
    );

    return result.routine_items ?? [];
}
