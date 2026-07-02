<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Calendar } from '@lucide/vue';
import MatrixSheet from '@/components/MatrixSheet.vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';

// M0-2: static display data only. Replaced by GetMatrixBoardQuery in M1.
const matrixAreas = ['仕事', '野球', 'バイオリン', 'プライベート'];

const matrixRows = [
    {
        key: 'monthly',
        label: '1ヶ月くらいの間でやるべきこと',
        isCurrent: false,
        isCheckable: false,
        cells: [
            ['移行に必要なタスクを完了', '負債対策'],
            ['身体を元に戻す'],
            [],
            [],
        ],
    },
    {
        key: 'current',
        label: '今やるべきこと',
        isCurrent: true,
        isCheckable: true,
        cells: [
            ['受注バグ修正', '課題の整理', '上野さん依頼対応', 'WEB注文変更'],
            ['死なない。', '生きて夢を追う。'],
            ['少しだけ', '1曲だけ弾く。'],
            [],
        ],
    },
    {
        key: 'future',
        label: '将来どうなっていたいか',
        isCurrent: false,
        isCheckable: false,
        cells: [
            ['残りシート、行列担当 完了', 'サンドボックス バグ修正'],
            ['最低限の筋トレ', '最低限のピッチング'],
            [],
            [],
        ],
    },
];

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
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4 md:px-6 md:pb-6"
    >
        <div class="flex items-start justify-between gap-4">
            <PageTitleOrnament title="Clear Dawn" align="left" />

            <p
                class="flex items-center gap-2 pt-4 font-serif text-lg tracking-[0.12em] text-cd-ink lining-nums"
            >
                {{ today }}
                <Calendar :size="19" :stroke-width="1.6" aria-hidden="true" />
            </p>
        </div>

        <MatrixSheet :areas="matrixAreas" :rows="matrixRows" />
    </div>
</template>
