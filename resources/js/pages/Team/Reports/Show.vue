<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import EmptyState from '@/components/team/EmptyState.vue';
import ReadonlyBanner from '@/components/team/ReadonlyBanner.vue';

interface AthleteRow {
    id: number;
    name: string;
    training_sessions: number;
    meal_record_days: number;
    condition_record_days: number;
    needs_follow_up: boolean;
}

interface Props {
    team: { name: string; slug: string };
    period: {
        days: number;
        timezone: string;
        from: string;
        to: string;
        follow_up_threshold_days: number;
    };
    summary: {
        athletes: number;
        training_sessions: number;
        meal_record_days: number;
        needs_follow_up: number;
    };
    athletes: AthleteRow[];
    disclaimer: string;
}

const props = defineProps<Props>();

function periodHref(days: number): string {
    return `/t/${props.team.slug}/reports?period=${days}`;
}
</script>

<template>
    <div>
        <Head title="レポート" />
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-medium text-violet-600">レポート</p>
                <h1 class="mt-1 text-2xl font-bold">チーム全体の集計</h1>
                <p class="mt-2 text-sm text-slate-500">
                    {{ period.from }} 〜 {{ period.to }}（{{
                        period.timezone
                    }}）
                </p>
            </div>
            <div class="flex gap-2">
                <Link
                    :href="periodHref(7)"
                    class="rounded-lg px-3 py-2 text-sm font-medium"
                    :class="
                        period.days === 7
                            ? 'bg-violet-600 text-white'
                            : 'border bg-white text-slate-600'
                    "
                >
                    7日
                </Link>
                <Link
                    :href="periodHref(30)"
                    class="rounded-lg px-3 py-2 text-sm font-medium"
                    :class="
                        period.days === 30
                            ? 'bg-violet-600 text-white'
                            : 'border bg-white text-slate-600'
                    "
                >
                    30日
                </Link>
            </div>
        </div>

        <div class="mt-4">
            <ReadonlyBanner />
        </div>

        <p
            class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        >
            {{ disclaimer }}
        </p>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border bg-white p-5">
                <p class="text-2xl font-bold">{{ summary.athletes }}</p>
                <p class="text-sm text-slate-500">在籍人数</p>
            </div>
            <div class="rounded-xl border bg-white p-5">
                <p class="text-2xl font-bold">
                    {{ summary.training_sessions }}
                </p>
                <p class="text-sm text-slate-500">トレーニング実施数</p>
            </div>
            <div class="rounded-xl border bg-white p-5">
                <p class="text-2xl font-bold">{{ summary.meal_record_days }}</p>
                <p class="text-sm text-slate-500">食事記録日の合計</p>
            </div>
            <div class="rounded-xl border bg-white p-5">
                <p class="text-2xl font-bold">{{ summary.needs_follow_up }}</p>
                <p class="text-sm text-slate-500">
                    記録確認が必要（{{
                        period.follow_up_threshold_days
                    }}日未満）
                </p>
            </div>
        </div>

        <EmptyState
            v-if="athletes.length === 0"
            class="mt-6"
            title="在籍選手がいません"
            description="選手が所属すると集計表が表示されます。"
        />

        <div v-else class="mt-6 overflow-x-auto rounded-xl border bg-white">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-3 font-medium">選手</th>
                        <th class="px-4 py-3 font-medium">練習回数</th>
                        <th class="px-4 py-3 font-medium">食事記録日</th>
                        <th class="px-4 py-3 font-medium">コンディション日</th>
                        <th class="px-4 py-3 font-medium">確認</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="athlete in athletes"
                        :key="athlete.id"
                        class="border-t"
                    >
                        <td class="px-4 py-3 font-medium">
                            {{ athlete.name }}
                        </td>
                        <td class="px-4 py-3">
                            {{ athlete.training_sessions }}
                        </td>
                        <td class="px-4 py-3">
                            {{ athlete.meal_record_days }}
                        </td>
                        <td class="px-4 py-3">
                            {{ athlete.condition_record_days }}
                        </td>
                        <td class="px-4 py-3">
                            <span
                                :class="
                                    athlete.needs_follow_up
                                        ? 'text-amber-700'
                                        : 'text-emerald-700'
                                "
                            >
                                {{
                                    athlete.needs_follow_up
                                        ? '要確認'
                                        : '問題なし'
                                }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
