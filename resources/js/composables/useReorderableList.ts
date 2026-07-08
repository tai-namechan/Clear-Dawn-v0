import { type Ref } from 'vue';
import { router } from '@inertiajs/vue3';

type ReorderableItem = { id: string };

type ReorderableListOptions = {
    items: Ref<ReorderableItem[]>;
    reorderUrl: string;
};

export function useReorderableList({ items, reorderUrl }: ReorderableListOptions) {
    function move(index: number, direction: -1 | 1): void {
        const target = index + direction;

        if (target < 0 || target >= items.value.length) {
            return;
        }

        const next = [...items.value];
        const [moved] = next.splice(index, 1);
        next.splice(target, 0, moved);
        items.value = next;

        commitOrder(next.map((item) => item.id));
    }

    function commitOrder(orderedIds: string[]): void {
        router.patch(
            reorderUrl,
            { ordered_ids: orderedIds },
            { preserveScroll: true },
        );
    }

    return { move };
}
