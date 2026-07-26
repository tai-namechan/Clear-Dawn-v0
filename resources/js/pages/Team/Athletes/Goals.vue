<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AthleteTabNav from '@/components/team/AthleteTabNav.vue';
import EmptyState from '@/components/team/EmptyState.vue';
import ReadonlyBanner from '@/components/team/ReadonlyBanner.vue';

interface GoalRow {
    id: string;
    name: string;
    starts_on: string | null;
    ends_on: string | null;
    status: string;
    status_label: string;
    progress_percent: number | null;
}

interface Props {
    team: { name: string; slug: string };
    athlete: { id: number; name: string; status: string };
    timezone: string;
    goals: GoalRow[];
}

defineProps<Props>();
</script>

<template>
    <div>
        <Head :title="`${athlete.name} / 目標`" />
        <div>
            <p class="text-sm font-medium text-violet-600">選手詳細</p>
            <h1 class="mt-1 text-2xl font-bold">{{ athlete.name }}</h1>
            <p class="mt-2 text-sm text-slate-500">
                公開可能な目標名と達成状況のみ（{{ timezone }}）
            </p>
        </div>

        <div class="mt-4">
            <ReadonlyBanner
                message="目標の個人的な動機や生の体重系指標は表示しません。"
            />
        </div>

        <AthleteTabNav
            :team-slug="team.slug"
            :athlete-id="athlete.id"
            active="goals"
        />

        <EmptyState
            v-if="goals.length === 0"
            class="mt-6"
            title="表示できる目標はありません"
            description="公開可能な目標がまだありません。"
        />

        <div v-else class="mt-6 overflow-x-auto rounded-xl border bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 font-medium">目標名</th>
                        <th class="px-4 py-3 font-medium">期間</th>
                        <th class="px-4 py-3 font-medium">状態</th>
                        <th class="px-4 py-3 font-medium">進捗</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="goal in goals" :key="goal.id" class="border-t">
                        <td class="px-4 py-3 font-medium">{{ goal.name }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ goal.starts_on ?? '—' }} 〜
                            {{ goal.ends_on ?? '期限なし' }}
                        </td>
                        <td class="px-4 py-3">{{ goal.status_label }}</td>
                        <td class="px-4 py-3">
                            {{
                                goal.progress_percent === null
                                    ? goal.status_label
                                    : `${goal.progress_percent}%`
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
