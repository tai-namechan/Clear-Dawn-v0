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
            ['面接対策を進める'],
            ['スクワットを継続する'],
            ['音階を丁寧に弾く'],
            ['部屋を片付ける'],
        ],
    },
    {
        key: 'current',
        label: '今やるべきこと',
        isCurrent: true,
        isCheckable: true,
        cells: [
            ['M1設計を確定する'],
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

const today = new Intl.DateTimeFormat('ja-JP', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    weekday: 'short',
}).format(new Date());
</script>

<template>
    <Head title="Dashboard" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4 pb-10 md:px-8"
    >
        <PageTitleOrnament
            title="Clear Dawn"
            subtitle="夜明け前の静けさの中で、今日やるべきことを決める"
        />

        <p
            class="flex items-center justify-center gap-1.5 text-xs tracking-wide text-muted-foreground"
        >
            <Calendar :size="14" :stroke-width="1.6" aria-hidden="true" />
            {{ today }}
        </p>

        <MatrixSheet :areas="matrixAreas" :rows="matrixRows" />
    </div>
</template>
