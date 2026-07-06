<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { SlidersHorizontal } from '@lucide/vue';
import { computed, ref } from 'vue';
import HeaderUserMenu from '@/components/HeaderUserMenu.vue';
import MatrixCellEditModal from '@/components/MatrixCellEditModal.vue';
import MatrixSheet from '@/components/MatrixSheet.vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import type { LifeArea, MatrixRow } from '@/types/matrix';
import { index as lifeAreasIndex } from '@/routes/life-areas';

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
const todayIso = [
    now.getFullYear(),
    String(now.getMonth() + 1).padStart(2, '0'),
    String(now.getDate()).padStart(2, '0'),
].join('-');
</script>

<template>
    <Head title="Dashboard" />

    <div
        class="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4 md:px-6 md:pb-6"
    >
        <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-4">
            <div class="flex items-start justify-between gap-6">
                <PageTitleOrnament
                    title="Clear Dawn"
                    align="left"
                    size="prominent"
                />

                <div
                    class="flex shrink-0 flex-wrap items-center justify-end gap-x-5 gap-y-2 pt-1.5 md:pt-2"
                >
                    <time
                        :datetime="todayIso"
                        class="font-serif text-lg tracking-[0.12em] text-cd-ink-muted lining-nums select-none"
                    >
                        {{ today }}
                    </time>

                    <Link
                        :href="lifeAreasIndex()"
                        aria-label="領域管理"
                        class="group flex items-center gap-1.5 font-serif text-base tracking-[0.12em] text-cd-ink-muted transition-colors hover:text-cd-ink"
                    >
                        <SlidersHorizontal
                            :size="16"
                            :stroke-width="1.6"
                            class="opacity-75 transition-opacity group-hover:opacity-100"
                            aria-hidden="true"
                        />
                        <span class="underline-offset-4 group-hover:underline">
                            領域管理
                        </span>
                    </Link>

                    <div
                        aria-hidden="true"
                        class="cd-header-divider hidden h-5 sm:block"
                    />

                    <HeaderUserMenu />
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
