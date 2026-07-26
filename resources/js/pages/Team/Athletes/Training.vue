<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AthleteTabNav from '@/components/team/AthleteTabNav.vue';
import EmptyState from '@/components/team/EmptyState.vue';
import ReadonlyBanner from '@/components/team/ReadonlyBanner.vue';

interface SessionRow {
    id: string;
    date: string;
    category: string;
    duration_minutes: number | null;
    intensity: string;
    status: string;
    status_label: string;
    summary: string;
}

interface Props {
    team: { name: string; slug: string };
    athlete: { id: number; name: string; status: string };
    period: { days: number; timezone: string; from: string; to: string };
    sessions: SessionRow[];
}

defineProps<Props>();
</script>

<template>
    <div>
        <Head :title="`${athlete.name} / トレーニング`" />
        <div>
            <p class="text-sm font-medium text-violet-600">選手詳細</p>
            <h1 class="mt-1 text-2xl font-bold">{{ athlete.name }}</h1>
            <p class="mt-2 text-sm text-slate-500">
                {{ period.from }} 〜 {{ period.to }}（{{ period.timezone }}）
            </p>
        </div>

        <div class="mt-4">
            <ReadonlyBanner />
        </div>

        <AthleteTabNav
            :team-slug="team.slug"
            :athlete-id="athlete.id"
            active="training"
        />

        <EmptyState
            v-if="sessions.length === 0"
            class="mt-6"
            title="対象期間に記録はありません"
            description="トレーニングセッションがまだありません。"
        />

        <div v-else class="mt-6 overflow-x-auto rounded-xl border bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 font-medium">日付</th>
                        <th class="px-4 py-3 font-medium">種目</th>
                        <th class="px-4 py-3 font-medium">時間</th>
                        <th class="px-4 py-3 font-medium">強度</th>
                        <th class="px-4 py-3 font-medium">状態</th>
                        <th class="px-4 py-3 font-medium">要約</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="session in sessions"
                        :key="session.id"
                        class="border-t"
                    >
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ session.date }}
                        </td>
                        <td class="px-4 py-3">{{ session.category }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{
                                session.duration_minutes === null
                                    ? '—'
                                    : `${session.duration_minutes}分`
                            }}
                        </td>
                        <td class="px-4 py-3">{{ session.intensity }}</td>
                        <td class="px-4 py-3">{{ session.status_label }}</td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ session.summary }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
