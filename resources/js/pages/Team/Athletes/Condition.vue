<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AthleteTabNav from '@/components/team/AthleteTabNav.vue';
import EmptyState from '@/components/team/EmptyState.vue';
import ReadonlyBanner from '@/components/team/ReadonlyBanner.vue';

interface EntryRow {
    id: string;
    date: string;
    recovery_level: string;
    training_availability: string;
}

interface Props {
    team: { name: string; slug: string };
    athlete: { id: number; name: string; status: string };
    period: { days: number; timezone: string; from: string; to: string };
    entries: EntryRow[];
    disclaimer: string;
}

defineProps<Props>();
</script>

<template>
    <div>
        <Head :title="`${athlete.name} / コンディション`" />
        <div>
            <p class="text-sm font-medium text-violet-600">選手詳細</p>
            <h1 class="mt-1 text-2xl font-bold">{{ athlete.name }}</h1>
            <p class="mt-2 text-sm text-slate-500">
                {{ period.from }} 〜 {{ period.to }}（{{ period.timezone }}）
            </p>
        </div>

        <div class="mt-4">
            <ReadonlyBanner
                message="症状の自由記述や医療判断に当たる表示はしません。段階ラベルのみです。"
            />
        </div>

        <AthleteTabNav
            :team-slug="team.slug"
            :athlete-id="athlete.id"
            active="condition"
        />

        <p class="mt-4 text-sm text-amber-800">{{ disclaimer }}</p>

        <EmptyState
            v-if="entries.length === 0"
            class="mt-6"
            title="対象期間に記録はありません"
            description="コンディション記録がまだありません。"
        />

        <div v-else class="mt-6 overflow-x-auto rounded-xl border bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 font-medium">日付</th>
                        <th class="px-4 py-3 font-medium">回復・疲労</th>
                        <th class="px-4 py-3 font-medium">トレーニング確認</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="entry in entries"
                        :key="entry.id"
                        class="border-t"
                    >
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ entry.date }}
                        </td>
                        <td class="px-4 py-3">{{ entry.recovery_level }}</td>
                        <td class="px-4 py-3">
                            {{ entry.training_availability }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
