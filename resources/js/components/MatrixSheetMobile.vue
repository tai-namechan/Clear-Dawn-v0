<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, Pencil, Sunrise } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toggle } from '@/routes/matrix-cell-items';
import type { LifeArea, MatrixCellItem, MatrixRow } from '@/types/matrix';

interface Props {
    areas: LifeArea[];
    rows: MatrixRow[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'edit', payload: { rowIndex: number; areaIndex: number }): void;
}>();

const selectedAreaIndex = ref(0);

watch(
    () => props.areas.length,
    (length) => {
        if (selectedAreaIndex.value >= length) {
            selectedAreaIndex.value = Math.max(0, length - 1);
        }
    },
);

const selectedArea = computed(
    () => props.areas[selectedAreaIndex.value] ?? null,
);

function selectArea(index: number): void {
    selectedAreaIndex.value = index;
}

function toggleCompletion(item: MatrixCellItem): void {
    router.patch(toggle.url(item.id), {}, { preserveScroll: true });
}

function openEditor(rowIndex: number): void {
    emit('edit', { rowIndex, areaIndex: selectedAreaIndex.value });
}
</script>

<template>
    <section
        aria-label="TOP Matrix（スマホ）"
        class="flex w-full flex-col gap-3"
    >
        <div
            v-if="areas.length === 0"
            class="rounded-[1.25rem] border border-cd-matrix-line bg-cd-matrix-surface px-4 py-8 text-center text-sm text-cd-ink-muted"
        >
            領域がまだありません。
        </div>

        <template v-else>
            <div
                class="sticky top-0 z-10 -mx-1 border-b border-cd-matrix-line/60 bg-cd-matrix-surface/95 px-1 pt-1 pb-2 backdrop-blur-sm"
                role="tablist"
                aria-label="領域"
            >
                <div
                    class="flex [scrollbar-width:none] gap-1 overflow-x-auto [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden"
                >
                    <button
                        v-for="(area, areaIndex) in areas"
                        :key="area.id"
                        type="button"
                        role="tab"
                        :aria-selected="areaIndex === selectedAreaIndex"
                        class="relative min-w-0 shrink-0 grow basis-0 rounded-t-lg px-3 py-2.5 font-serif text-sm tracking-[0.08em] transition-colors"
                        :class="
                            areaIndex === selectedAreaIndex
                                ? 'bg-cd-matrix-surface text-cd-ink'
                                : 'bg-transparent text-cd-ink-muted'
                        "
                        @click="selectArea(areaIndex)"
                    >
                        <span class="block truncate">{{ area.name }}</span>
                        <span
                            aria-hidden="true"
                            class="absolute inset-x-2 bottom-0 h-0.5 rounded-full transition-colors"
                            :class="
                                areaIndex === selectedAreaIndex
                                    ? 'bg-cd-matrix-accent'
                                    : 'bg-transparent'
                            "
                        />
                    </button>
                </div>
            </div>

            <div
                v-if="selectedArea"
                class="flex flex-col gap-3"
                role="tabpanel"
                :aria-label="selectedArea.name"
            >
                <article
                    v-for="(row, rowIndex) in rows"
                    :key="row.key"
                    class="overflow-hidden rounded-[1.15rem] border border-cd-matrix-line bg-cd-matrix-surface"
                    :class="{ 'cd-matrix-row-current': row.key === 'current' }"
                >
                    <div
                        class="flex items-start justify-between gap-3 border-b border-cd-matrix-line/70 px-4 py-3"
                        :class="
                            row.key === 'current'
                                ? 'cd-matrix-row-current-label bg-cd-matrix-row-current text-cd-ink'
                                : 'bg-cd-matrix-row-header text-cd-ink'
                        "
                    >
                        <div
                            class="inline-flex min-w-0 items-center gap-2 font-matrix text-base leading-snug"
                        >
                            <Sunrise
                                v-if="row.key === 'current'"
                                :size="16"
                                :stroke-width="1.6"
                                aria-hidden="true"
                                class="shrink-0 text-cd-matrix-accent"
                            />
                            <span class="text-balance">{{ row.label }}</span>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 rounded-md border border-cd-matrix-line/70 bg-cd-matrix-surface/90 p-1.5 text-cd-ink-muted"
                            :aria-label="`${selectedArea.name} × ${row.label} を編集`"
                            @click="openEditor(rowIndex)"
                        >
                            <Pencil
                                :size="14"
                                :stroke-width="1.7"
                                aria-hidden="true"
                            />
                        </button>
                    </div>

                    <div class="flex flex-col gap-3 px-4 py-4">
                        <ul
                            v-if="row.cells[selectedAreaIndex]?.items.length"
                            class="flex flex-col gap-3"
                        >
                            <li
                                v-for="item in row.cells[selectedAreaIndex]
                                    .items"
                                :key="item.id"
                                class="flex items-start gap-3 text-[15px] leading-relaxed"
                            >
                                <button
                                    v-if="row.is_checkable"
                                    type="button"
                                    role="checkbox"
                                    :aria-checked="item.is_completed"
                                    :aria-label="`${item.title} を${item.is_completed ? '再開' : '完了'}にする`"
                                    class="mt-1 inline-flex size-4 shrink-0 items-center justify-center rounded-[3px] border transition-colors"
                                    :class="
                                        item.is_completed
                                            ? 'border-cd-matrix-accent bg-cd-matrix-accent-soft text-cd-matrix-accent'
                                            : 'border-cd-ink-muted/55 bg-transparent'
                                    "
                                    @click="toggleCompletion(item)"
                                >
                                    <Check
                                        v-if="item.is_completed"
                                        :size="12"
                                        :stroke-width="2.4"
                                        aria-hidden="true"
                                    />
                                </button>
                                <button
                                    type="button"
                                    class="min-w-0 flex-1 text-left"
                                    @click="openEditor(rowIndex)"
                                >
                                    <span
                                        :class="
                                            item.is_completed
                                                ? 'font-matrix--done font-matrix line-through decoration-cd-ink-muted/60'
                                                : 'font-matrix'
                                        "
                                        >{{ item.title }}</span
                                    >
                                </button>
                            </li>
                        </ul>
                        <button
                            v-else
                            type="button"
                            class="w-full text-left font-sans text-sm text-cd-ink-muted"
                            @click="openEditor(rowIndex)"
                        >
                            まだ項目がありません。タップして追加できます。
                        </button>
                    </div>
                </article>
            </div>
        </template>
    </section>
</template>
