<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { SlidersHorizontal } from '@lucide/vue';
import { computed, ref } from 'vue';
import HeaderUserMenu from '@/components/HeaderUserMenu.vue';
import MatrixCellEditModal from '@/components/MatrixCellEditModal.vue';
import MatrixSheet from '@/components/MatrixSheet.vue';
import { index as lifeAreasIndex } from '@/routes/life-areas';
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
    <Head title="ダッシュボード" />

    <div
        class="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4 md:px-6 md:pb-6"
    >
        <div
            class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-7 md:gap-8"
        >
            <div
                class="flex flex-col items-start justify-between gap-6 pt-1 md:flex-row md:items-center md:pt-3"
            >
                <div class="flex flex-col items-start gap-3">
                    <h1
                        class="font-serif text-[2.75rem] leading-none font-normal tracking-[0.14em] text-cd-dawn-deep md:text-[3.25rem]"
                    >
                        Clear Dawn
                    </h1>
                    <div
                        aria-hidden="true"
                        class="cd-mask-ornament h-6 w-64 text-cd-gilt md:w-80"
                    />
                    <p
                        class="font-sans text-sm tracking-[0.08em] text-cd-ink-muted md:text-[0.95rem]"
                    >
                        多忙な中、思考を整理し、夜明けへ導く。
                    </p>
                </div>

                <div
                    class="flex shrink-0 flex-wrap items-center gap-3 md:gap-4"
                >
                    <time
                        :datetime="todayIso"
                        class="cursor-default font-serif text-base tracking-[0.12em] text-cd-ink lining-nums select-none md:text-lg"
                    >
                        {{ today }}
                    </time>

                    <Link
                        :href="lifeAreasIndex()"
                        aria-label="領域管理"
                        class="group inline-flex items-center gap-1.5 font-serif text-base tracking-[0.12em] text-cd-ink-muted transition-colors hover:text-cd-dawn-deep md:text-lg"
                    >
                        <SlidersHorizontal
                            :size="16"
                            :stroke-width="1.6"
                            class="opacity-70 transition-opacity group-hover:opacity-100"
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
                :description="editingRowDescription"
                :is-checkable="editingRowIsCheckable"
                @update:open="(value) => (editing = value ? editing : null)"
            />
        </div>
    </div>
</template>
