import { fetchInertiaPageProps } from '@/lib/inertiaPageFetch';
import type { RoutineItem } from '@/types/routine';

/**
 * Inertia ページ props から実施項目一覧を取得する（部分リロード用）。
 */
export async function fetchRoutineItemsFromPage(): Promise<RoutineItem[]> {
    const props = await fetchInertiaPageProps<{ routineItems?: RoutineItem[] }>(
        '/routine-items',
    );

    return props.routineItems ?? [];
}
