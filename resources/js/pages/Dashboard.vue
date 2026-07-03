<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Calendar, SlidersHorizontal } from '@lucide/vue';
import { computed, ref } from 'vue';
import MatrixCellEditModal from '@/components/MatrixCellEditModal.vue';
import MatrixSheet from '@/components/MatrixSheet.vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import { index as lifeAreasIndex } from '@/routes/life-areas';
import type { LifeArea, MatrixRow } from '@/types/matrix';

interface Props {
    areas: LifeArea[];
    rows: MatrixRow[];
}

const props = defineProps<Props>();

const editing = ref<{ rowIndex: number; areaIndex: number } | null>(null);

// モーダル表示中も Inertia の props 更新（項目追加・編集・削除）を反映するため、
// セルはスナップショットではなく props から都度導出する
const editingCell = computed(() =>
    editing.value !== null
        ? (props.rows[editing.value.rowIndex]?.cells[editing.value.areaIndex] ??
          null)
        : null,
);

const editingAreaName = computed(() =>
    editing.value !== null
        ? (props.areas[editing.value.areaIndex]?.name ?? '')
        : '',
);

const editingRowLabel = computed(() =>
    editing.value !== null
        ? (props.rows[editing.value.rowIndex]?.label ?? '')
        : '',
);

const editingRowIsCheckable = computed(() =>
    editing.value !== null
        ? (props.rows[editing.value.rowIndex]?.is_checkable ?? false)
        : false,
);

function openCellEditor(payload: { rowIndex: number; areaIndex: number }) {
    editing.value = payload;
}

const now = new Date();
const today = [
    now.getFullYear(),
    String(now.getMonth() + 1).padStart(2, '0'),
    String(now.getDate()).padStart(2, '0'),
].join('/');
</script>

<template>
    <Head title="Dashboard" />

    <div
        class="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4 md:px-6 md:pb-6"
    >
        <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-4">
            <div class="flex items-start justify-between gap-4">
                <PageTitleOrnament title="Clear Dawn" align="left" />

                <div class="flex items-center gap-4 pt-2.5">
                    <p
                        class="flex items-center gap-2 font-serif text-base tracking-[0.12em] text-cd-ink-muted lining-nums"
                    >
                        {{ today }}
                        <Calendar
                            :size="17"
                            :stroke-width="1.6"
                            aria-hidden="true"
                        />
                    </p>
                    <Link
                        :href="lifeAreasIndex()"
                        aria-label="領域管理"
                        class="flex items-center gap-2 rounded-md px-2 py-1.5 font-serif text-sm tracking-[0.12em] text-cd-ink-muted transition-colors hover:bg-muted/70 hover:text-cd-ink"
                    >
                        <SlidersHorizontal
                            :size="16"
                            :stroke-width="1.6"
                            aria-hidden="true"
                        />
                        領域管理
                    </Link>
                </div>
            </div>

            <MatrixSheet :areas="areas" :rows="rows" @edit="openCellEditor" />

            <MatrixCellEditModal
                :open="editing !== null"
                :cell="editingCell"
                :area-name="editingAreaName"
                :row-label="editingRowLabel"
                :is-checkable="editingRowIsCheckable"
                @update:open="(value) => (editing = value ? editing : null)"
            />
        </div>
    </div>
</template>
