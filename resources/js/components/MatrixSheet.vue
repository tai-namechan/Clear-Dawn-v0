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
        class="cd-shadow-soft flex min-h-[30rem] w-full flex-1 flex-col overflow-hidden rounded-xl border border-cd-line bg-cd-surface md:min-h-[34rem]"
    >
        <table class="h-full w-full table-fixed border-collapse font-serif">
            <thead>
                <tr class="h-16 border-b border-cd-line/70">
                    <th
                        scope="col"
                        class="w-40 bg-muted/60 px-4 py-4 md:w-52"
                    ></th>
                    <th
                        v-for="area in areas"
                        :key="area.id"
                        scope="col"
                        class="border-l border-cd-line/60 bg-muted/60 px-4 py-4 text-center text-lg font-normal tracking-[0.24em] text-cd-ink"
                    >
                        {{ area.name }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(row, rowIndex) in rows"
                    :key="row.key"
                    class="h-1/3 border-b border-cd-line/60 last:border-b-0"
                    :class="{ 'cd-matrix-row-current': row.key === 'current' }"
                >
                    <th
                        scope="row"
                        class="px-5 py-7 text-center align-middle font-normal"
                        :class="{ 'bg-muted/60': row.key !== 'current' }"
                    >
                        <span
                            class="inline-flex items-center justify-center gap-2 text-base leading-loose tracking-[0.1em] text-cd-ink lining-nums md:text-lg"
                        >
                            <Sunrise
                                v-if="row.key === 'current'"
                                :size="20"
                                :stroke-width="1.6"
                                aria-hidden="true"
                                class="shrink-0 text-cd-sunrise"
                            />
                            {{ row.label }}
                        </span>
                    </th>
                    <td
                        v-for="(cell, areaIndex) in row.cells"
                        :key="areas[areaIndex].id"
                        class="group/cell relative border-l border-cd-line/60 px-5 py-7 align-middle"
                    >
                        <button
                            type="button"
                            class="absolute top-2 right-2 rounded-md p-1.5 text-cd-ink-muted/70 opacity-0 transition-opacity group-hover/cell:opacity-100 hover:bg-muted/70 hover:text-cd-ink focus-visible:opacity-100"
                            :aria-label="`${areas[areaIndex].name} × ${row.label} を編集`"
                            @click="emit('edit', { rowIndex, areaIndex })"
                        >
                            <Pencil
                                :size="15"
                                :stroke-width="1.6"
                                aria-hidden="true"
                            />
                        </button>

                        <ul
                            v-if="cell.items.length > 0"
                            class="flex flex-col gap-4"
                            :class="
                                row.is_checkable
                                    ? 'mx-auto w-fit items-start'
                                    : 'items-center'
                            "
                        >
                            <li
                                v-for="item in cell.items"
                                :key="item.id"
                                class="flex items-start gap-3 font-sans text-[15px] leading-relaxed tracking-normal text-cd-ink lining-nums"
                            >
                                <button
                                    v-if="row.is_checkable"
                                    type="button"
                                    role="checkbox"
                                    :aria-checked="item.is_completed"
                                    :aria-label="`${item.title} を${item.is_completed ? '再開' : '完了'}にする`"
                                    class="mt-1.5 inline-flex size-4 shrink-0 items-center justify-center rounded-[3px] border transition-colors"
                                    :class="
                                        item.is_completed
                                            ? 'border-cd-sunrise bg-cd-sunrise text-white'
                                            : 'border-cd-ink-muted/70 bg-white/70'
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
                                <span
                                    :class="[
                                        row.is_checkable
                                            ? 'text-left'
                                            : 'text-center text-balance',
                                        item.is_completed
                                            ? 'text-cd-ink-muted line-through decoration-cd-ink-muted/60'
                                            : '',
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
