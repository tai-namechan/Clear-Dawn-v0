<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AthleteTabNav from '@/components/team/AthleteTabNav.vue';
import ReadonlyBanner from '@/components/team/ReadonlyBanner.vue';

interface WindowSummary {
    days: number;
    timezone: string;
    from: string;
    to: string;
    training: {
        sessions: number;
        completed_sessions: number;
        trend_label: string;
    };
    meals: {
        recorded_days: number;
        continuity_label: string;
        needs_follow_up: boolean;
    };
    condition: {
        recorded_days: number;
        latest_recovery_level: string;
        trend_label: string;
    };
}

interface Props {
    team: { name: string; slug: string };
    athlete: { id: number; name: string; status: string };
    windows: WindowSummary[];
    disclaimer: string;
}

defineProps<Props>();
</script>

<template>
    <div>
        <Head :title="`${athlete.name} / レポート`" />
        <div>
            <p class="text-sm font-medium text-violet-600">選手詳細</p>
            <h1 class="mt-1 text-2xl font-bold">{{ athlete.name }}</h1>
            <p class="mt-2 text-sm text-slate-500">
                直近7日 / 30日の安全な集計
            </p>
        </div>

        <div class="mt-4">
            <ReadonlyBanner />
        </div>

        <AthleteTabNav
            :team-slug="team.slug"
            :athlete-id="athlete.id"
            active="report"
        />

        <p
            class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        >
            {{ disclaimer }}
        </p>

        <div class="mt-6 grid gap-4 xl:grid-cols-2">
            <section
                v-for="window in windows"
                :key="window.days"
                class="rounded-xl border bg-white p-5"
            >
                <h2 class="text-lg font-semibold">直近{{ window.days }}日</h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ window.from }} 〜 {{ window.to }}
                </p>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4 border-b pb-2">
                        <dt class="text-slate-500">トレーニング</dt>
                        <dd class="text-right font-medium">
                            {{ window.training.sessions }}回（完了
                            {{ window.training.completed_sessions }}） /
                            {{ window.training.trend_label }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b pb-2">
                        <dt class="text-slate-500">食事記録継続</dt>
                        <dd class="text-right font-medium">
                            {{ window.meals.recorded_days }}日 /
                            {{ window.meals.continuity_label }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">コンディション傾向</dt>
                        <dd class="text-right font-medium">
                            {{ window.condition.latest_recovery_level }} /
                            {{ window.condition.trend_label }}
                        </dd>
                    </div>
                </dl>
            </section>
        </div>
    </div>
</template>
