<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, Pencil, Sunrise } from '@lucide/vue';
import { toggle } from '@/routes/matrix-cell-items';
import type { LifeArea, MatrixCellItem, MatrixRow } from '@/types/matrix';

interface Props {
    areas: LifeArea[];
    rows: MatrixRow[];
}

defineProps<Props>();

const emit = defineEmits<{
    (e: 'edit', payload: { rowIndex: number; areaIndex: number }): void;
}>();

function toggleCompletion(item: MatrixCellItem): void {
    router.patch(toggle.url(item.id), {}, { preserveScroll: true });
}
</script>

<template>
    <section
        aria-label="TOP Matrix"
        class="cd-shadow-soft flex min-h-[30rem] w-full flex-1 flex-col overflow-hidden rounded-[1.25rem] border border-cd-matrix-line bg-cd-matrix-surface landscape-compact:min-h-0 landscape-compact:overflow-auto md:min-h-[34rem] landscape-compact:md:min-h-0"
    >
        <table class="h-full w-full table-fixed border-collapse">
            <thead>
                <tr
                    class="h-[3.75rem] border-b border-cd-matrix-line landscape-compact:h-11"
                >
                    <th
                        scope="col"
                        class="w-40 bg-cd-matrix-column-header px-4 py-4 landscape-compact:w-28 landscape-compact:px-2 landscape-compact:py-2 md:w-56 landscape-compact:md:w-28"
                    ></th>
                    <th
                        v-for="area in areas"
                        :key="area.id"
                        scope="col"
                        class="border-l border-cd-matrix-line bg-cd-matrix-column-header px-3 py-4 text-center align-middle font-normal landscape-compact:px-1.5 landscape-compact:py-2"
                    >
                        <span
                            class="font-serif text-base tracking-[0.14em] text-cd-matrix-header-foreground landscape-compact:text-sm landscape-compact:tracking-[0.1em] md:text-lg landscape-compact:md:text-sm"
                        >
                            {{ area.name }}
                        </span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(row, rowIndex) in rows"
                    :key="row.key"
                    class="h-1/3 border-b border-cd-matrix-line/60 last:border-b-0"
                    :class="{ 'cd-matrix-row-current': row.key === 'current' }"
                >
                    <th
                        scope="row"
                        class="relative px-5 py-6 text-center align-middle font-normal landscape-compact:px-2 landscape-compact:py-2.5"
                        :class="
                            row.key === 'current'
                                ? 'cd-matrix-row-current-label bg-cd-matrix-row-current'
                                : 'bg-cd-matrix-row-header'
                        "
                    >
                        <span
                            class="inline-flex items-center justify-center gap-2 font-matrix text-base leading-snug lining-nums landscape-compact:gap-1 landscape-compact:text-sm landscape-compact:leading-tight md:text-lg landscape-compact:md:text-sm"
                        >
                            <Sunrise
                                v-if="row.key === 'current'"
                                :size="18"
                                :stroke-width="1.6"
                                aria-hidden="true"
                                class="shrink-0 text-cd-matrix-accent landscape-compact:size-3.5"
                            />
                            {{ row.label }}
                        </span>
                    </th>
                    <td
                        v-for="(cell, areaIndex) in row.cells"
                        :key="areas[areaIndex].id"
                        class="cd-matrix-cell group/cell relative cursor-pointer border-l border-cd-matrix-line px-5 py-6 align-middle landscape-compact:px-2.5 landscape-compact:py-2.5"
                        @click="emit('edit', { rowIndex, areaIndex })"
                    >
                        <button
                            type="button"
                            class="pointer-events-none absolute top-2.5 right-2.5 rounded-md border border-cd-matrix-line/60 bg-cd-matrix-surface/90 p-1.5 text-cd-ink-muted opacity-0 shadow-xs transition-all duration-200 group-hover/cell:pointer-events-auto group-hover/cell:opacity-100 hover:border-cd-matrix-line hover:bg-white hover:text-cd-ink focus-visible:pointer-events-auto focus-visible:opacity-100 landscape-compact:top-1.5 landscape-compact:right-1.5 landscape-compact:p-1"
                            :aria-label="`${areas[areaIndex].name} × ${row.label} を編集`"
                            @click.stop="emit('edit', { rowIndex, areaIndex })"
                        >
                            <Pencil
                                :size="15"
                                :stroke-width="1.7"
                                aria-hidden="true"
                            />
                        </button>

                        <ul
                            v-if="cell.items.length > 0"
                            class="flex flex-col gap-4 landscape-compact:gap-2"
                            :class="
                                row.is_checkable
                                    ? 'mx-auto w-fit items-start'
                                    : 'items-center'
                            "
                        >
                            <li
                                v-for="item in cell.items"
                                :key="item.id"
                                class="flex items-start gap-3 text-[17px] leading-relaxed lining-nums landscape-compact:gap-2 landscape-compact:text-sm landscape-compact:leading-snug md:text-lg landscape-compact:md:text-sm"
                            >
                                <button
                                    v-if="row.is_checkable"
                                    type="button"
                                    role="checkbox"
                                    :aria-checked="item.is_completed"
                                    :aria-label="`${item.title} を${item.is_completed ? '再開' : '完了'}にする`"
                                    class="mt-1.5 inline-flex size-4 shrink-0 items-center justify-center rounded-[3px] border transition-colors landscape-compact:mt-0.5 landscape-compact:size-3.5"
                                    :class="
                                        item.is_completed
                                            ? 'border-cd-matrix-accent bg-cd-matrix-accent-soft text-cd-matrix-accent'
                                            : 'border-cd-ink-muted/55 bg-transparent hover:border-cd-matrix-accent/70'
                                    "
                                    @click.stop="toggleCompletion(item)"
                                >
                                    <Check
                                        v-if="item.is_completed"
                                        :size="12"
                                        :stroke-width="2.4"
                                        aria-hidden="true"
                                    />
                                </button>
                                <span
                                    :class="[
                                        row.is_checkable
                                            ? 'text-left'
                                            : 'text-center text-balance',
                                        item.is_completed
                                            ? 'font-matrix--done font-matrix line-through decoration-cd-ink-muted/60'
                                            : 'font-matrix',
                                    ]"
                                    >{{ item.title }}</span
                                >
                            </li>
                        </ul>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
