<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AthleteTabNav from '@/components/team/AthleteTabNav.vue';
import EmptyState from '@/components/team/EmptyState.vue';
import ReadonlyBanner from '@/components/team/ReadonlyBanner.vue';

interface DayRow {
    date: string;
    has_record: boolean;
    meal_count: number;
}

interface Props {
    team: { name: string; slug: string };
    athlete: { id: number; name: string; status: string };
    period: { days: number; timezone: string; from: string; to: string };
    summary: {
        recorded_days: number;
        total_meals: number;
        average_meals_per_recorded_day: number;
        current_streak_days: number;
    };
    days: DayRow[];
}

defineProps<Props>();
</script>

<template>
    <div>
        <Head :title="`${athlete.name} / 食事`" />
        <div>
            <p class="text-sm font-medium text-violet-600">選手詳細</p>
            <h1 class="mt-1 text-2xl font-bold">{{ athlete.name }}</h1>
            <p class="mt-2 text-sm text-slate-500">
                {{ period.from }} 〜 {{ period.to }}（{{ period.timezone }}）
            </p>
        </div>

        <div class="mt-4">
            <ReadonlyBanner
                message="食品名・量・画像・自由記述は表示しません。記録の有無と回数のみです。"
            />
        </div>

        <AthleteTabNav
            :team-slug="team.slug"
            :athlete-id="athlete.id"
            active="meals"
        />

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border bg-white p-5">
                <p class="text-2xl font-bold">{{ summary.recorded_days }}日</p>
                <p class="text-sm text-slate-500">記録日数</p>
            </div>
            <div class="rounded-xl border bg-white p-5">
                <p class="text-2xl font-bold">{{ summary.total_meals }}回</p>
                <p class="text-sm text-slate-500">食事回数合計</p>
            </div>
            <div class="rounded-xl border bg-white p-5">
                <p class="text-2xl font-bold">
                    {{ summary.average_meals_per_recorded_day }}
                </p>
                <p class="text-sm text-slate-500">記録日あたり平均回数</p>
            </div>
            <div class="rounded-xl border bg-white p-5">
                <p class="text-2xl font-bold">
                    {{ summary.current_streak_days }}日
                </p>
                <p class="text-sm text-slate-500">現在の連続記録</p>
            </div>
        </div>

        <EmptyState
            v-if="summary.recorded_days === 0"
            class="mt-6"
            title="対象期間に記録はありません"
            description="食事記録日がまだありません。"
        />

        <div v-else class="mt-6 overflow-x-auto rounded-xl border bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 font-medium">日付</th>
                        <th class="px-4 py-3 font-medium">記録</th>
                        <th class="px-4 py-3 font-medium">食事回数</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="day in days" :key="day.date" class="border-t">
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ day.date }}
                        </td>
                        <td class="px-4 py-3">
                            {{ day.has_record ? 'あり' : 'なし' }}
                        </td>
                        <td class="px-4 py-3">{{ day.meal_count }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
