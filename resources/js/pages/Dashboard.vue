<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Calendar } from '@lucide/vue';
import MatrixSheet from '@/components/MatrixSheet.vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

// M0-2: static display data only. Replaced by GetMatrixBoardQuery in M1.
const matrixAreas = ['仕事', '野球', 'バイオリン', 'プライベート'];

const matrixRows = [
    {
        key: 'monthly',
        label: '1ヶ月くらいの間でやるべきこと',
        isCurrent: false,
        isCheckable: false,
        cells: [
            ['M1設計を確定する', '面接対策を進める'],
            ['スクワットを継続する', '遠投で肩を強くする'],
            ['音階を丁寧に弾く', '課題曲の譜読みを進める'],
            ['部屋を片付ける', '家計を見直す'],
        ],
    },
    {
        key: 'current',
        label: '今やるべきこと',
        isCurrent: true,
        isCheckable: true,
        cells: [
            ['今日の設計論点をまとめる'],
            ['ブルペンで投球確認'],
            ['開放弦の基礎練習'],
            ['睡眠リズムを整える'],
        ],
    },
    {
        key: 'future',
        label: '将来どうなっていたいか',
        isCurrent: false,
        isCheckable: false,
        cells: [
            ['仕事で自信を持って価値を出せる'],
            ['150km/hに近づく身体を作る'],
            ['音楽を自由に表現できる'],
            ['心身と生活が整っている'],
        ],
    },
];

const now = new Date();
const weekday = new Intl.DateTimeFormat('ja-JP', { weekday: 'short' }).format(
    now,
);
const today = [
    now.getFullYear(),
    String(now.getMonth() + 1).padStart(2, '0'),
    String(now.getDate()).padStart(2, '0'),
].join('.');
</script>

<template>
    <Head title="Dashboard" />

    <div
        class="flex h-full flex-1 flex-col gap-5 overflow-x-auto rounded-xl p-4 pb-12 md:px-10"
    >
        <div class="relative">
            <p
                class="flex items-center justify-end gap-1.5 pt-2 font-serif text-sm tracking-[0.14em] text-cd-ink-muted lining-nums md:absolute md:top-3 md:right-0"
            >
                <Calendar :size="15" :stroke-width="1.6" aria-hidden="true" />
                {{ today }}（{{ weekday }}）
            </p>

            <PageTitleOrnament
                title="Clear Dawn"
                subtitle="夜明け前の静けさの中で、今日やるべきことを決める"
            />
        </div>

        <MatrixSheet :areas="matrixAreas" :rows="matrixRows" />
    </div>
</template>
