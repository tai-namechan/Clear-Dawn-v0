import { router } from '@inertiajs/vue3';
import type { Ref } from 'vue';

export interface ReorderableItem {
    id: string;
}

export function useReorderableList<T extends ReorderableItem>(
    items: Ref<T[]>,
    reorderUrl: string,
) {
    function commitOrder(ids: string[]): void {
        router.patch(
            reorderUrl,
            { ordered_ids: ids },
            { preserveScroll: true },
        );
    }

    function applyOrder(ids: string[]): void {
        const byId = new Map(items.value.map((item) => [item.id, item]));
        const reordered = ids
            .map((id) => byId.get(id))
            .filter((item): item is T => item != null);

        if (reordered.length !== items.value.length) {
            return;
        }

        items.value = reordered;
        commitOrder(ids);
    }

    function move(index: number, direction: -1 | 1): void {
        const ids = items.value.map((item) => item.id);
        const target = index + direction;

        if (target < 0 || target >= ids.length) {
            return;
        }

        [ids[index], ids[target]] = [ids[target], ids[index]];
        applyOrder(ids);
    }

    function moveToIndex(oldIndex: number, newIndex: number): void {
        if (oldIndex === newIndex) {
            return;
        }

        const ids = items.value.map((item) => item.id);
        const [moved] = ids.splice(oldIndex, 1);
        ids.splice(newIndex, 0, moved);
        applyOrder(ids);
    }

    return { move, moveToIndex, applyOrder };
}
