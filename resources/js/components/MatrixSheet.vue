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
        class="cd-frost cd-shadow-soft mx-auto w-full max-w-6xl overflow-hidden rounded-2xl border border-cd-line/70"
    >
        <table class="w-full table-fixed border-collapse">
            <thead>
                <tr class="border-b border-cd-line/60">
                    <th scope="col" class="w-40 px-4 py-5 md:w-56"></th>
                    <th
                        v-for="area in areas"
                        :key="area"
                        scope="col"
                        class="border-l border-cd-line/40 px-4 py-5 text-center font-serif text-base font-normal tracking-[0.12em] text-cd-ink"
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
                        class="px-4 py-8 text-left align-middle font-normal md:px-5"
                    >
                        <span
                            class="flex items-center gap-2 font-serif text-sm leading-relaxed tracking-wide text-cd-ink"
                        >
                            <Sunrise
                                v-if="row.isCurrent"
                                :size="18"
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
                        class="border-l border-cd-line/40 px-4 py-8 align-middle md:px-6"
                    >
                        <ul class="flex flex-col items-center gap-3">
                            <li
                                v-for="item in cell"
                                :key="item"
                                class="flex items-start gap-2 text-sm leading-relaxed text-cd-ink"
                            >
                                <span
                                    v-if="row.isCheckable"
                                    aria-hidden="true"
                                    class="mt-1 inline-block size-3.5 shrink-0 rounded-sm border border-cd-sunrise/60 bg-cd-surface/70"
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
