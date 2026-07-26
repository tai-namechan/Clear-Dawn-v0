<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Search, Users } from '@lucide/vue';
import { ref } from 'vue';
interface Athlete {
    id: number;
    name: string;
    training_sessions_7_days: number;
    meal_record_days_7_days: number;
    last_recorded_at: string | null;
    status: string;
}
const props = defineProps<{
    team: { name: string; slug: string };
    filters: { search?: string };
    athletes: {
        data: Athlete[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        total: number;
    };
}>();
const search = ref(props.filters.search ?? '');
function submit() {
    router.get(
        `/t/${props.team.slug}/athletes`,
        { search: search.value },
        { preserveState: true, replace: true },
    );
}
</script>
<template>
    <div>
        <Head title="選手一覧" />
        <div>
            <p class="text-sm font-medium text-violet-600">Roster</p>
            <h1 class="mt-1 text-2xl font-bold">選手一覧</h1>
            <p class="mt-2 text-sm text-slate-500">
                所属中の選手 {{ athletes.total }}名
            </p>
        </div>
        <form class="mt-6 flex max-w-md gap-2" @submit.prevent="submit">
            <label class="relative flex-1"
                ><span class="sr-only">氏名検索</span
                ><Search
                    class="absolute top-3 left-3 text-slate-400"
                    :size="18" /><input
                    v-model="search"
                    class="w-full rounded-lg border border-slate-300 bg-white py-2.5 pr-3 pl-10 text-sm focus:border-violet-500 focus:ring-2 focus:ring-violet-200"
                    placeholder="選手名で検索" /></label
            ><button
                class="rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white"
            >
                検索
            </button>
        </form>
        <div
            class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
        >
            <div v-if="athletes.data.length === 0" class="p-12 text-center">
                <Users class="mx-auto text-slate-300" />
                <p class="mt-3 font-semibold">該当する選手はいません</p>
                <p class="mt-1 text-sm text-slate-500">
                    検索条件を変えてください。
                </p>
            </div>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead
                        class="border-b bg-slate-50 text-xs tracking-wide text-slate-500 uppercase"
                    >
                        <tr>
                            <th class="px-5 py-3">選手</th>
                            <th class="px-5 py-3">練習 / 7日</th>
                            <th class="px-5 py-3">食事記録 / 7日</th>
                            <th class="px-5 py-3">体重傾向</th>
                            <th class="px-5 py-3">最終記録</th>
                            <th class="px-5 py-3">状態</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="athlete in athletes.data"
                            :key="athlete.id"
                            class="hover:bg-violet-50/40"
                        >
                            <td class="px-5 py-4">
                                <Link
                                    :href="`/t/${team.slug}/athletes/${athlete.id}`"
                                    class="font-semibold text-slate-900 hover:text-violet-700"
                                    >{{ athlete.name }}</Link
                                >
                            </td>
                            <td class="px-5 py-4">
                                {{ athlete.training_sessions_7_days }}回
                            </td>
                            <td class="px-5 py-4">
                                {{ athlete.meal_record_days_7_days }}日
                            </td>
                            <td class="px-5 py-4 text-slate-500">
                                共有設定待ち
                            </td>
                            <td class="px-5 py-4 text-slate-500">
                                {{
                                    athlete.last_recorded_at ??
                                    '対象期間に記録なし'
                                }}
                            </td>
                            <td class="px-5 py-4">
                                <span
                                    class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700"
                                    >在籍中</span
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <nav v-if="athletes.links.length > 3" class="mt-4 flex flex-wrap gap-1">
            <Link
                v-for="link in athletes.links"
                :key="link.label"
                :href="link.url ?? '#'"
                class="rounded border px-3 py-1.5 text-sm"
                :class="
                    link.active
                        ? 'border-violet-600 bg-violet-600 text-white'
                        : 'bg-white text-slate-600'
                "
            >
                <span v-html="link.label" />
            </Link>
        </nav>
    </div>
</template>
