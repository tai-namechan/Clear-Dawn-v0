<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MatrixCellEditModal from '@/components/MatrixCellEditModal.vue';
import MatrixSheet from '@/components/MatrixSheet.vue';
import MatrixSheetMobile from '@/components/MatrixSheetMobile.vue';
import type { LifeArea, MatrixRow } from '@/types/matrix';

interface Props {
    areas: LifeArea[];
    rows: MatrixRow[];
}

const props = defineProps<Props>();

const editing = ref<{ rowIndex: number; areaIndex: number } | null>(null);

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

const modalDescriptions: Record<MatrixRow['key'], string> = {
    monthly:
        '中期的に取り組むことを設定しましょう。\n1ヶ月ほどのスパンで、着実に進めていきましょう。',
    current:
        '今日・今週に集中することを整理しましょう。\nまずここから、一歩を踏み出しましょう。',
    future: '理想の自分や未来の姿を描きましょう。\n向かいたい方向を、静かに見据えましょう。',
};

const editingRowDescription = computed(() => {
    if (editing.value === null) {
        return '';
    }

    const key = props.rows[editing.value.rowIndex]?.key;

    return key ? modalDescriptions[key] : '';
});

function openCellEditor(payload: { rowIndex: number; areaIndex: number }) {
    editing.value = payload;
}
</script>

<template>
    <Head title="ダッシュボード" />

    <div
        class="flex h-full min-h-0 flex-1 flex-col p-4 landscape-compact:p-3 landscape-compact:pb-3 md:px-6 md:pb-8"
    >
        <div
            class="mx-auto flex w-full max-w-7xl min-h-0 flex-1 flex-col gap-6 landscape-compact:gap-3 md:gap-7"
        >
            <!-- Desktop / tablet: keep the classic matrix table -->
            <div class="hidden min-h-0 flex-1 flex-col md:flex">
                <MatrixSheet :areas="areas" :rows="rows" @edit="openCellEditor" />
            </div>

            <!-- Phone only (< md): stacked area tabs + row cards -->
            <div class="md:hidden">
                <MatrixSheetMobile
                    :areas="areas"
                    :rows="rows"
                    @edit="openCellEditor"
                />
            </div>
            <MatrixCellEditModal
                :open="editing !== null"
                :cell="editingCell"
                :area-name="editingAreaName"
                :row-label="editingRowLabel"
                :description="editingRowDescription"
                :is-checkable="editingRowIsCheckable"
                @update:open="(value) => (editing = value ? editing : null)"
            />
        </div>
    </div>
</template>
