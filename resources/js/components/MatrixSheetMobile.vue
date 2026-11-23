<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    BookOpen,
    Briefcase,
    Check,
    HeartPulse,
    Music,
    Pencil,
    Sparkles,
    UserRound,
    Volleyball,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, ref, watch } from 'vue';
import {
    areaIconKey,
    cellItemCount,
    focusTabLabel,
} from '@/lib/homeMatrix.mjs';
import { hapticTap } from '@/lib/native/haptics';
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

const AREA_ICONS: Record<string, Component> = {
    ball: Volleyball,
    music: Music,
    study: BookOpen,
    health: HeartPulse,
    work: Briefcase,
    private: UserRound,
    default: Sparkles,
};

const selectedAreaIndex = ref(0);
const selectedRowKey = ref<MatrixRow['key']>('current');

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
const selectedRowIndex = computed(() =>
    Math.max(
        0,
        props.rows.findIndex((row) => row.key === selectedRowKey.value),
    ),
);
const selectedRow = computed(() => props.rows[selectedRowIndex.value] ?? null);
const selectedItems = computed(
    () => selectedRow.value?.cells[selectedAreaIndex.value]?.items ?? [],
);

function areaIcon(area: LifeArea): Component {
    return AREA_ICONS[areaIconKey(area.name)] ?? Sparkles;
}

function currentCount(areaIndex: number): number {
    return cellItemCount(props.rows, 'current', areaIndex);
}

function toggleCompletion(item: MatrixCellItem): void {
    void hapticTap();
    router.patch(toggle.url(item.id), {}, { preserveScroll: true });
}

function openEditor(): void {
    emit('edit', {
        rowIndex: selectedRowIndex.value,
        areaIndex: selectedAreaIndex.value,
    });
}
</script>

<template>
    <section
        aria-label="人生のマトリクス（スマホ）"
        class="flex w-full flex-col gap-4"
    >
        <div
            v-if="areas.length === 0"
            class="cd-panel px-4 py-8 text-center text-sm text-cd-ink-muted"
        >
            領域がまだありません。
        </div>

        <template v-else>
            <!-- Life areas as tiles: choose the area to focus on -->
            <div class="cd-panel p-4">
                <h2 class="font-sans text-lg font-bold text-cd-ink">
                    人生のマトリクス
                </h2>
                <div
                    class="mt-3 grid grid-cols-2 gap-3"
                    role="tablist"
                    aria-label="領域"
                >
                    <button
                        v-for="(area, areaIndex) in areas"
                        :key="area.id"
                        type="button"
                        role="tab"
                        :aria-selected="areaIndex === selectedAreaIndex"
                        class="cd-home-area-tile"
                        @click="selectedAreaIndex = areaIndex"
                    >
                        <component
                            :is="areaIcon(area)"
                            :size="26"
                            :stroke-width="1.7"
                            aria-hidden="true"
                            class="cd-home-area-icon"
                        />
                        <span
                            class="mt-2 block truncate font-sans text-base font-bold text-cd-ink"
                            >{{ area.name }}</span
                        >
                        <span
                            class="mt-0.5 block font-sans text-xs text-cd-ink-muted"
                            >今やること {{ currentCount(areaIndex) }}件</span
                        >
                    </button>
                </div>
            </div>

            <!-- This month / now / future for the chosen area -->
            <div v-if="selectedArea" class="cd-panel p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="font-sans text-lg font-bold text-cd-ink">
                            今月・今・将来のフォーカス
                        </h2>
                        <p class="mt-0.5 font-sans text-xs text-cd-ink-muted">
                            {{ selectedArea.name }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex shrink-0 items-center gap-1 rounded-full border border-[var(--cd-hero-edge)] px-3 py-1.5 font-sans text-xs font-semibold text-cd-ink-muted transition-colors hover:text-cd-ink"
                        :aria-label="`${selectedArea.name} × ${selectedRow?.label ?? ''} を編集`"
                        @click="openEditor"
                    >
                        <Pencil
                            :size="13"
                            :stroke-width="1.8"
                            aria-hidden="true"
                        />
                        編集
                    </button>
                </div>

                <div class="cd-segment mt-3" role="tablist" aria-label="期間">
                    <button
                        v-for="row in rows"
                        :key="row.key"
                        type="button"
                        role="tab"
                        :aria-selected="row.key === selectedRowKey"
                        @click="selectedRowKey = row.key"
                    >
                        {{ focusTabLabel(row.key, row.label) }}
                    </button>
                </div>

                <p class="mt-3 font-sans text-xs text-cd-ink-muted">
                    {{ selectedRow?.label }}
                </p>

                <ul v-if="selectedItems.length" class="mt-1 flex flex-col">
                    <li
                        v-for="item in selectedItems"
                        :key="item.id"
                        class="flex items-center gap-3 border-b border-[#efecf5] py-3 last:border-b-0"
                    >
                        <button
                            v-if="selectedRow?.is_checkable"
                            type="button"
                            role="checkbox"
                            :aria-checked="item.is_completed"
                            :aria-label="`${item.title} を${item.is_completed ? '再開' : '完了'}にする`"
                            class="inline-flex size-5 shrink-0 items-center justify-center rounded-md border transition-colors"
                            :class="
                                item.is_completed
                                    ? 'border-[var(--cd-hero-primary)] bg-[var(--cd-hero-primary)] text-white'
                                    : 'border-cd-ink-muted/45 bg-white'
                            "
                            @click="toggleCompletion(item)"
                        >
                            <Check
                                v-if="item.is_completed"
                                :size="13"
                                :stroke-width="2.6"
                                aria-hidden="true"
                            />
                        </button>
                        <span
                            v-else
                            aria-hidden="true"
                            class="size-1.5 shrink-0 rounded-full bg-[var(--cd-hero-primary)]"
                        />
                        <button
                            type="button"
                            class="min-w-0 flex-1 text-left font-matrix text-base leading-snug"
                            :class="{
                                'text-cd-ink-muted line-through decoration-cd-ink-muted/60':
                                    item.is_completed,
                            }"
                            @click="openEditor"
                        >
                            {{ item.title }}
                        </button>
                    </li>
                </ul>
                <button
                    v-else
                    type="button"
                    class="mt-2 w-full rounded-2xl border border-dashed border-[var(--cd-hero-edge)] px-4 py-5 text-center font-sans text-sm text-cd-ink-muted"
                    @click="openEditor"
                >
                    まだ項目がありません。タップして追加できます。
                </button>
            </div>
        </template>
    </section>
</template>
