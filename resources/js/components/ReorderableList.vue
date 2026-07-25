<script setup lang="ts" generic="T extends ReorderableItem">
import { ArrowDown, ArrowUp, GripVertical } from '@lucide/vue';
import Sortable from 'sortablejs';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import type { ComponentPublicInstance } from 'vue';
import { Button } from '@/components/ui/button';
import {
    useReorderableList,
} from '@/composables/useReorderableList';
import type { ReorderableItem } from '@/composables/useReorderableList';

interface Props {
    items: readonly T[];
    reorderUrl: string;
    disabled?: boolean;
    itemLabel?: (item: T) => string | undefined;
    variant?: 'list' | 'table';
    itemClass?: string | ((item: T) => string | undefined);
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
    variant: 'list',
});

const containerRef = ref<HTMLElement | null>(null);
const localItems = ref<T[]>([...props.items]);
const displayItems = computed(() => localItems.value as T[]);

defineSlots<{
    row(props: { item: T; index: number }): unknown;
    actions(props: { item: T; index: number }): unknown;
}>();

let sortable: Sortable | null = null;

const { move, moveToIndex } = useReorderableList(localItems, props.reorderUrl);

watch(
    () => props.items,
    (nextItems) => {
        localItems.value = [...nextItems];
    },
    { deep: true },
);

watch(
    () => props.disabled,
    (isDisabled) => {
        if (isDisabled) {
            sortable?.option('disabled', true);

            return;
        }

        if (sortable) {
            sortable.option('disabled', false);

            return;
        }

        initSortable();
    },
);

function resolveItemClass(item: T): string | undefined {
    if (typeof props.itemClass === 'function') {
        return props.itemClass(item);
    }

    return props.itemClass;
}

function labelFor(item: T): string | undefined {
    return props.itemLabel?.(item);
}

function initSortable(): void {
    if (!containerRef.value || props.disabled) {
        return;
    }

    sortable?.destroy();

    sortable = Sortable.create(containerRef.value, {
        animation: 180,
        handle: '.reorder-handle',
        draggable: '.reorderable-item',
        ghostClass: 'reorderable-ghost',
        chosenClass: 'reorderable-chosen',
        dragClass: 'reorderable-drag',
        forceFallback: false,
        onEnd(event) {
            if (
                event.oldIndex == null ||
                event.newIndex == null ||
                event.oldIndex === event.newIndex
            ) {
                return;
            }

            moveToIndex(event.oldIndex, event.newIndex);
        },
    });
}

onMounted(() => {
    initSortable();
});

onBeforeUnmount(() => {
    sortable?.destroy();
    sortable = null;
});

function setContainerRef(element: Element | ComponentPublicInstance | null) {
    containerRef.value = element as HTMLElement | null;
}
</script>

<template>
    <tbody
        v-if="variant === 'table'"
        :ref="setContainerRef"
        class="reorderable-list"
    >
        <tr
            v-for="(item, index) in displayItems"
            :key="item.id"
            class="reorderable-item border-b border-cd-line/40 last:border-b-0"
            :class="resolveItemClass(item)"
            :data-id="item.id"
        >
            <slot name="row" :item="item" :index="index" />
            <td v-if="!disabled" class="px-4 py-3">
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        class="reorder-handle inline-flex shrink-0 cursor-grab touch-none rounded-sm p-1 text-cd-ink-muted transition-colors hover:text-cd-ink active:cursor-grabbing"
                        :aria-label="
                            labelFor(item)
                                ? `${labelFor(item)} をドラッグして並べ替え`
                                : 'ドラッグして並べ替え'
                        "
                    >
                        <GripVertical :size="15" :stroke-width="1.6" />
                    </button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        :disabled="index === 0"
                        :aria-label="
                            labelFor(item)
                                ? `${labelFor(item)} を上へ`
                                : '上へ移動'
                        "
                        @click="move(index, -1)"
                    >
                        <ArrowUp :size="15" :stroke-width="1.6" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        :disabled="index === displayItems.length - 1"
                        :aria-label="
                            labelFor(item)
                                ? `${labelFor(item)} を下へ`
                                : '下へ移動'
                        "
                        @click="move(index, 1)"
                    >
                        <ArrowDown :size="15" :stroke-width="1.6" />
                    </Button>
                    <slot name="actions" :item="item" :index="index" />
                </div>
            </td>
            <td v-else class="px-4 py-3">
                <slot name="actions" :item="item" :index="index" />
            </td>
        </tr>
    </tbody>

    <ul
        v-else
        :ref="setContainerRef"
        class="reorderable-list flex flex-col"
    >
        <li
            v-for="(item, index) in displayItems"
            :key="item.id"
            class="reorderable-item border-b border-cd-line/60 px-5 py-4 last:border-b-0"
            :class="resolveItemClass(item)"
            :data-id="item.id"
        >
            <div class="flex items-center justify-between gap-3">
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <button
                        v-if="!disabled"
                        type="button"
                        class="reorder-handle inline-flex shrink-0 cursor-grab touch-none rounded-sm p-1 text-cd-ink-muted transition-colors hover:text-cd-ink active:cursor-grabbing"
                        :aria-label="
                            labelFor(item)
                                ? `${labelFor(item)} をドラッグして並べ替え`
                                : 'ドラッグして並べ替え'
                        "
                    >
                        <GripVertical :size="16" :stroke-width="1.6" />
                    </button>
                    <div class="min-w-0 flex-1">
                        <slot name="row" :item="item" :index="index" />
                    </div>
                </div>
                <div
                    v-if="!disabled"
                    class="flex shrink-0 items-center gap-1"
                >
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :disabled="index === 0"
                        :aria-label="
                            labelFor(item)
                                ? `${labelFor(item)} を上へ`
                                : '上へ移動'
                        "
                        @click="move(index, -1)"
                    >
                        <ArrowUp :size="15" :stroke-width="1.6" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :disabled="index === displayItems.length - 1"
                        :aria-label="
                            labelFor(item)
                                ? `${labelFor(item)} を下へ`
                                : '下へ移動'
                        "
                        @click="move(index, 1)"
                    >
                        <ArrowDown :size="15" :stroke-width="1.6" />
                    </Button>
                    <slot name="actions" :item="item" :index="index" />
                </div>
                <div v-else class="flex shrink-0 items-center gap-1">
                    <slot name="actions" :item="item" :index="index" />
                </div>
            </div>
        </li>
    </ul>
</template>

<style scoped>
.reorderable-ghost {
    opacity: 0.45;
    background-color: color-mix(in oklab, var(--muted) 70%, transparent);
}

.reorderable-chosen {
    background-color: color-mix(in oklab, var(--muted) 35%, transparent);
}

.reorderable-drag {
    opacity: 1;
}
</style>
