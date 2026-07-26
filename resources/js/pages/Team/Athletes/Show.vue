<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Dumbbell, Utensils, HeartPulse, Scale } from '@lucide/vue';
defineProps<{
    athlete: {
        id: number;
        name: string;
        status: string;
        training_sessions_7_days: number;
        meal_record_days_7_days: number;
        condition_recorded: boolean;
        follow_up: boolean;
        last_updated_at: string | null;
        weight_sharing_status: string;
    };
}>();
const tabs = [
    '概要',
    'トレーニング',
    '食事',
    'コンディション',
    '目標',
    'レポート',
];
</script>
<template>
    <div>
        <Head :title="athlete.name" />
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-medium text-violet-600">選手詳細</p>
                <div class="mt-1 flex items-center gap-3">
                    <h1 class="text-2xl font-bold">{{ athlete.name }}</h1>
                    <span
                        class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700"
                        >在籍中</span
                    >
                </div>
                <p class="mt-2 text-sm text-slate-500">
                    最終更新:
                    {{ athlete.last_updated_at ?? '対象期間に記録なし' }}
                </p>
            </div>
        </div>
        <div class="mt-6 overflow-x-auto border-b">
            <div class="flex min-w-max gap-6">
                <button
                    v-for="(tab, index) in tabs"
                    :key="tab"
                    class="border-b-2 px-1 py-3 text-sm font-medium"
                    :class="
                        index === 0
                            ? 'border-violet-600 text-violet-700'
                            : 'border-transparent text-slate-500'
                    "
                    :disabled="index !== 0"
                >
                    {{ tab }}
                </button>
            </div>
        </div>
        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border bg-white p-5">
                <Dumbbell class="text-violet-600" :size="20" />
                <p class="mt-4 text-2xl font-bold">
                    {{ athlete.training_sessions_7_days }}回
                </p>
                <p class="text-sm text-slate-500">7日間の練習</p>
            </div>
            <div class="rounded-xl border bg-white p-5">
                <Utensils class="text-violet-600" :size="20" />
                <p class="mt-4 text-2xl font-bold">
                    {{ athlete.meal_record_days_7_days }}日
                </p>
                <p class="text-sm text-slate-500">7日間の食事記録</p>
            </div>
            <div class="rounded-xl border bg-white p-5">
                <HeartPulse class="text-violet-600" :size="20" />
                <p class="mt-4 text-lg font-bold">
                    {{
                        athlete.follow_up ? '本人へ確認' : '記録上の要確認なし'
                    }}
                </p>
                <p class="text-sm text-slate-500">痛み・違和感の安全な要約</p>
            </div>
            <div class="rounded-xl border bg-white p-5">
                <Scale class="text-violet-600" :size="20" />
                <p class="mt-4 text-lg font-bold">共有されていません</p>
                <p class="text-sm text-slate-500">体重の7日傾向</p>
            </div>
        </div>
        <section
            class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-5"
        >
            <h2 class="font-semibold text-amber-900">共有範囲について</h2>
            <p class="mt-2 text-sm leading-6 text-amber-800">
                保護者同意と共有ポリシーは後続Phaseです。このプロトタイプは記録の有無と安全な要約だけを表示し、食品名・量・毎日の体重・自由記述を返しません。
            </p>
        </section>
    </div>
</template>
