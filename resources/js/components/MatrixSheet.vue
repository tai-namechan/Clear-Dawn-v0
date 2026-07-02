<script setup lang="ts">
import { Sunrise } from '@lucide/vue';

interface MatrixRow {
    key: string;
    label: string;
    isCurrent: boolean;
    isCheckable: boolean;
    /** Cell items aligned with the order of `areas`. Empty cells are allowed. */
    cells: string[][];
}

interface Props {
    areas: string[];
    rows: MatrixRow[];
}

defineProps<Props>();
</script>

<template>
    <section
        aria-label="TOP Matrix"
        class="cd-shadow-soft flex min-h-[28rem] w-full flex-1 flex-col overflow-hidden rounded-xl border border-cd-line bg-cd-surface md:min-h-[32rem]"
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
                        :key="area"
                        scope="col"
                        class="border-l border-cd-line/60 bg-muted/60 px-4 py-4 text-center text-lg font-normal tracking-[0.24em] text-cd-ink"
                    >
                        {{ area }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="row in rows"
                    :key="row.key"
                    class="h-1/3 border-b border-cd-line/60 last:border-b-0"
                    :class="{ 'cd-matrix-row-current': row.isCurrent }"
                >
                    <th
                        scope="row"
                        class="px-5 py-7 text-center align-middle font-normal"
                        :class="{ 'bg-muted/60': !row.isCurrent }"
                    >
                        <span
                            class="inline-flex items-center justify-center gap-2 text-base leading-loose tracking-[0.1em] text-cd-ink lining-nums md:text-lg"
                        >
                            <Sunrise
                                v-if="row.isCurrent"
                                :size="20"
                                :stroke-width="1.6"
                                aria-hidden="true"
                                class="shrink-0 text-cd-sunrise"
                            />
                            {{ row.label }}
                        </span>
                    </th>
                    <td
                        v-for="(cell, index) in row.cells"
                        :key="areas[index]"
                        class="border-l border-cd-line/60 px-5 py-7 align-middle"
                    >
                        <ul
                            v-if="cell.length > 0"
                            class="flex flex-col gap-4"
                            :class="
                                row.isCheckable
                                    ? 'mx-auto w-fit items-start'
                                    : 'items-center'
                            "
                        >
                            <li
                                v-for="item in cell"
                                :key="item"
                                class="flex items-start gap-3 font-sans text-[15px] leading-relaxed tracking-normal text-cd-ink lining-nums"
                            >
                                <span
                                    v-if="row.isCheckable"
                                    aria-hidden="true"
                                    class="mt-1.5 inline-block size-4 shrink-0 rounded-[3px] border border-cd-ink-muted/70 bg-white/70"
                                />
                                <span
                                    :class="
                                        row.isCheckable
                                            ? 'text-left'
                                            : 'text-center text-balance'
                                    "
                                    >{{ item }}</span
                                >
                            </li>
                        </ul>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
