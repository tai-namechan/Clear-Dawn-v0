<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CircleAlert, Dumbbell, Users, Utensils } from '@lucide/vue';
import { computed } from 'vue';
import type { Component } from 'vue';

interface Team {
    name: string;
    slug: string;
}

interface Summary {
    athletes: number;
    training_records: number;
    meal_record_days: number;
    needs_follow_up: number;
}

interface SummaryCard {
    label: string;
    value: number;
    unit: string;
    icon: Component;
    iconClass: string;
    iconBackgroundClass: string;
}

const props = defineProps<{
    team: Team;
    summary: Summary;
}>();

const cards = computed<SummaryCard[]>(() => [
    {
        label: '在籍選手',
        value: props.summary.athletes,
        unit: '名',
        icon: Users,
        iconClass: 'text-violet-600',
        iconBackgroundClass: 'bg-violet-50',
    },
    {
        label: '7日間の練習記録',
        value: props.summary.training_records,
        unit: '件',
        icon: Dumbbell,
        iconClass: 'text-sky-600',
        iconBackgroundClass: 'bg-sky-50',
    },
    {
        label: '7日間の食事記録日',
        value: props.summary.meal_record_days,
        unit: '日',
        icon: Utensils,
        iconClass: 'text-emerald-600',
        iconBackgroundClass: 'bg-emerald-50',
    },
    {
        label: '記録確認が必要',
        value: props.summary.needs_follow_up,
        unit: '名',
        icon: CircleAlert,
        iconClass: 'text-amber-600',
        iconBackgroundClass: 'bg-amber-50',
    },
]);
</script>

<template>
    <div>
        <Head title="チーム概要" />

        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-medium text-violet-600">チーム概要</p>
                <h1 class="mt-1 text-2xl font-bold">今週の状況</h1>
                <p class="mt-2 text-sm text-slate-500">
                    事実に基づく記録状況のみを表示しています。
                </p>
            </div>
            <Link
                :href="`/t/${team.slug}/athletes`"
                class="rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-violet-700"
            >
                選手一覧を見る
            </Link>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="card in cards"
                :key="card.label"
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div
                    class="flex size-10 items-center justify-center rounded-lg"
                    :class="card.iconBackgroundClass"
                >
                    <component
                        :is="card.icon"
                        :size="20"
                        :class="card.iconClass"
                        aria-hidden="true"
                    />
                </div>
                <p
                    class="mt-4 flex items-baseline gap-1 text-3xl font-bold text-slate-900"
                >
                    {{ card.value }}
                    <span class="text-sm font-medium text-slate-500">{{
                        card.unit
                    }}</span>
                </p>
                <p class="mt-1 text-sm text-slate-500">{{ card.label }}</p>
            </article>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-3">
            <section
                class="rounded-xl border border-slate-200 bg-white p-5 xl:col-span-2"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-semibold">プロトタイプで確認できること</h2>
                    <span
                        class="rounded-full bg-violet-50 px-2.5 py-1 text-xs font-medium text-violet-700"
                    >
                        ローカルレビュー版
                    </span>
                </div>
                <ul
                    class="mt-5 grid gap-3 text-sm text-slate-600 sm:grid-cols-2"
                >
                    <li class="rounded-lg bg-slate-50 p-3">
                        チーム専用ログインと個人版セッションの分離
                    </li>
                    <li class="rounded-lg bg-slate-50 p-3">
                        所属中の選手だけを対象にした一覧・検索
                    </li>
                    <li class="rounded-lg bg-slate-50 p-3">
                        7日間の練習・食事記録の集計
                    </li>
                    <li class="rounded-lg bg-slate-50 p-3">
                        安全な要約だけを表示する選手詳細
                    </li>
                </ul>
            </section>
            <section class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="font-semibold">要確認</h2>
                <p class="mt-4 text-sm leading-6 text-slate-600">
                    7日間の食事記録が3日未満の選手を「記録確認が必要」として集計しています。健康状態の判定ではありません。
                </p>
            </section>
        </div>
    </div>
</template>
