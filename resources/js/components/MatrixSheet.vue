<script setup lang="ts">
import { Sunrise } from '@lucide/vue';

interface MatrixRow {
    key: string;
    label: string;
    isCurrent: boolean;
    isCheckable: boolean;
    /** Cell items aligned with the order of `areas`. */
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
        class="cd-frost cd-shadow-soft mx-auto w-full max-w-7xl overflow-hidden rounded-2xl border border-cd-line/70"
    >
        <table class="w-full table-fixed border-collapse">
            <thead>
                <tr class="border-b border-cd-line/70">
                    <th scope="col" class="w-40 px-5 py-6 md:w-60"></th>
                    <th
                        v-for="area in areas"
                        :key="area"
                        scope="col"
                        class="border-l border-cd-line/40 px-5 py-6 text-center font-serif text-lg font-normal tracking-[0.2em] text-cd-ink"
                    >
                        {{ area }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="row in rows"
                    :key="row.key"
                    class="border-b border-cd-line/50 last:border-b-0"
                    :class="{ 'cd-matrix-row-current': row.isCurrent }"
                >
                    <th
                        scope="row"
                        class="px-5 py-10 text-left align-middle font-normal md:px-6"
                    >
                        <span
                            class="flex items-start gap-2 font-serif text-base leading-relaxed tracking-[0.06em] text-cd-ink lining-nums"
                        >
                            <Sunrise
                                v-if="row.isCurrent"
                                :size="20"
                                :stroke-width="1.6"
                                aria-hidden="true"
                                class="mt-1.5 shrink-0 text-cd-sunrise"
                            />
                            {{ row.label }}
                        </span>
                    </th>
                    <td
                        v-for="(cell, index) in row.cells"
                        :key="areas[index]"
                        class="border-l border-cd-line/40 px-5 py-10 align-middle md:px-7"
                    >
                        <ul class="flex flex-col gap-3.5">
                            <li
                                v-for="item in cell"
                                :key="item"
                                class="flex items-start gap-2.5 text-sm leading-relaxed tracking-[0.02em] text-cd-ink"
                            >
                                <span
                                    v-if="row.isCheckable"
                                    aria-hidden="true"
                                    class="mt-0.5 inline-block size-4 shrink-0 rounded-[5px] border border-cd-sunrise/55 bg-cd-surface/80 shadow-[inset_0_1px_2px_hsl(22_62%_61%/0.08)]"
                                />
                                <span
                                    v-else
                                    aria-hidden="true"
                                    class="mt-2.5 inline-block size-1 shrink-0 rounded-full bg-cd-ink-muted/50"
                                />
                                <span>{{ item }}</span>
                            </li>
                        </ul>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
