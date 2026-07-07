<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Briefcase,
    Check,
    Home,
    Music,
    Pencil,
    Plus,
    Sunrise,
    Volleyball,
} from '@lucide/vue';
import type { Component } from 'vue';
import type { LifeArea, MatrixCellItem, MatrixRow } from '@/types/matrix';
import { toggle } from '@/routes/matrix-cell-items';

interface Props {
    areas: LifeArea[];
    rows: MatrixRow[];
}

defineProps<Props>();

const emit = defineEmits<{
    (e: 'edit', payload: { rowIndex: number; areaIndex: number }): void;
}>();

// 既定の領域名に対応する線画アイコン（ユーザーが改名した領域はアイコン無しで表示）
const areaIcons: Record<string, Component> = {
    仕事: Briefcase,
    野球: Volleyball,
    バイオリン: Music,
    プライベート: Home,
};

// 行キーごとの補助説明（ラベルは backend の MatrixRowKey enum が正）
const rowDescriptions: Record<MatrixRow['key'], string> = {
    monthly: '中期的に取り組むこと',
    current: '今日・今週に集中すること',
    future: '理想の自分・未来の姿',
};

function toggleCompletion(item: MatrixCellItem): void {
    router.patch(toggle.url(item.id), {}, { preserveScroll: true });
}
</script>

<template>
    <section
        aria-label="TOP Matrix"
        class="cd-shadow-soft flex min-h-[30rem] w-full flex-1 flex-col overflow-hidden rounded-2xl border border-cd-line bg-cd-surface md:min-h-[34rem]"
    >
        <table class="h-full w-full table-fixed border-collapse">
            <thead>
                <tr class="h-16 border-b border-cd-line/70">
                    <th
                        scope="col"
                        class="w-40 bg-muted/50 px-4 py-4 md:w-56"
                    ></th>
                    <th
                        v-for="area in areas"
                        :key="area.id"
                        scope="col"
                        class="border-l border-cd-line/50 bg-muted/50 px-3 py-4 text-center align-middle font-normal"
                    >
                        <span
                            class="inline-flex items-center justify-center gap-2 font-serif text-base tracking-[0.18em] text-cd-ink md:text-lg"
                        >
                            <component
                                :is="areaIcons[area.name]"
                                v-if="areaIcons[area.name]"
                                :size="18"
                                :stroke-width="1.5"
                                aria-hidden="true"
                                class="shrink-0 text-cd-ink-muted"
                            />
                            {{ area.name }}
                        </span>
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
                        class="px-5 py-6 text-center align-middle font-normal"
                        :class="{ 'bg-muted/50': row.key !== 'current' }"
                    >
                        <span class="flex flex-col items-center gap-1.5">
                            <span
                                class="inline-flex items-center justify-center gap-2 font-serif text-base leading-snug tracking-[0.1em] text-cd-ink lining-nums md:text-lg"
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
                            <span
                                class="font-sans text-xs leading-relaxed tracking-wide text-cd-ink-muted"
                            >
                                {{ rowDescriptions[row.key] }}
                            </span>
                        </span>
                    </th>
                    <!-- セル全体をタップ / クリックで編集モーダルを開く（iPad 等のタッチ端末対応）。
                         キーボード操作は内側の鉛筆ボタンで担保する -->
                    <td
                        v-for="(cell, areaIndex) in row.cells"
                        :key="areas[areaIndex].id"
                        class="cd-matrix-cell group/cell relative cursor-pointer border-l border-cd-line/50 px-5 py-6 align-middle"
                        @click="emit('edit', { rowIndex, areaIndex })"
                    >
                        <button
                            type="button"
                            class="absolute top-2 right-2 rounded-md border border-cd-line/70 bg-white/70 p-1.5 text-cd-ink-muted opacity-60 shadow-xs transition-all group-hover/cell:opacity-100 hover:border-cd-line hover:bg-white hover:text-cd-ink"
                            :aria-label="`${areas[areaIndex].name} × ${row.label} を編集`"
                            @click.stop="emit('edit', { rowIndex, areaIndex })"
                        >
                            <Pencil
                                :size="16"
                                :stroke-width="1.7"
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
                                            : 'border-cd-ink-muted/70 bg-white/80'
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
                                            ? 'text-cd-ink-muted line-through decoration-cd-ink-muted/60'
                                            : '',
                                    ]"
                                    >{{ item.title }}</span
                                >
                            </li>
                        </ul>

                        <!-- 空セル：未完成感を出しすぎず、次の一手を静かに示す -->
                        <div v-else class="flex items-center justify-center">
                            <span
                                aria-hidden="true"
                                class="inline-flex items-center gap-1.5 font-sans text-sm text-cd-ink-muted/55 transition-colors group-hover/cell:text-cd-ink-muted"
                            >
                                <Plus :size="14" :stroke-width="1.8" />
                                項目を追加
                            </span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
