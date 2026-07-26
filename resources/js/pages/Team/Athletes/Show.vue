<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Dumbbell, Flag, HeartPulse, Utensils } from '@lucide/vue';
import AthleteTabNav from '@/components/team/AthleteTabNav.vue';
import ReadonlyBanner from '@/components/team/ReadonlyBanner.vue';

interface Props {
    team: { name: string; slug: string };
    athlete: {
        id: number;
        name: string;
        status: string;
        training_sessions_7_days: number;
        meal_record_days_7_days: number;
        condition_summary: string;
        condition_recorded: boolean;
        follow_up: boolean;
        goals_active_count: number;
        goals_achieved_count: number;
        last_updated_at: string | null;
        weight_sharing_status: string;
    };
}

defineProps<Props>();
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
                    {{
                        athlete.last_updated_at
                            ? new Date(athlete.last_updated_at).toLocaleString(
                                  'ja-JP',
                              )
                            : '対象期間に記録なし'
                    }}
                </p>
            </div>
        </div>

        <div class="mt-4">
            <ReadonlyBanner />
        </div>

        <AthleteTabNav
            :team-slug="team.slug"
            :athlete-id="athlete.id"
            active="overview"
        />

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
                    {{ athlete.condition_summary }}
                </p>
                <p class="text-sm text-slate-500">
                    {{
                        athlete.follow_up
                            ? '本人へ確認を推奨'
                            : '記録上の要確認なし'
                    }}
                </p>
            </div>
            <div class="rounded-xl border bg-white p-5">
                <Flag class="text-violet-600" :size="20" />
                <p class="mt-4 text-lg font-bold">
                    進行 {{ athlete.goals_active_count }} / 達成
                    {{ athlete.goals_achieved_count }}
                </p>
                <p class="text-sm text-slate-500">目標進捗</p>
            </div>
        </div>

        <section
            class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-5"
        >
            <h2 class="font-semibold text-amber-900">共有範囲について</h2>
            <p class="mt-2 text-sm leading-6 text-amber-800">
                保護者同意と共有ポリシーは後続Phaseです。このプロトタイプは記録の有無と安全な要約だけを表示し、食品名・量・毎日の体重・自由記述を返しません。体重共有:
                {{
                    athlete.weight_sharing_status === 'sharing_not_configured'
                        ? '共有設定待ち'
                        : athlete.weight_sharing_status
                }}
            </p>
        </section>
    </div>
</template>
