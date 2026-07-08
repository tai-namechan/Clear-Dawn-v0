import { router } from '@inertiajs/vue3';
import type { Ref } from 'vue';

interface ReorderableItem {
    id: string;
}

export function useReorderableList(
    items: Ref<readonly ReorderableItem[]>,
    reorderUrl: string,
) {
    function move(index: number, direction: -1 | 1): void {
        const ids = items.value.map((item) => item.id);
        const target = index + direction;

        if (target < 0 || target >= ids.length) {
            return;
        }

        [ids[index], ids[target]] = [ids[target], ids[index]];

        router.patch(
            reorderUrl,
            { ordered_ids: ids },
            { preserveScroll: true },
        );
    }

    return { move };
}
