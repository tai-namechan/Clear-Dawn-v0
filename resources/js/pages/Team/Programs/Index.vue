<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import EmptyState from '@/components/team/EmptyState.vue';
import ReadonlyBanner from '@/components/team/ReadonlyBanner.vue';

interface ProgramRow {
    id: string;
    title: string;
    starts_on: string | null;
    ends_on: string | null;
    visibility_status: string;
    visibility_label: string;
    summary: string | null;
    item_titles: string[];
    item_count: number;
    assigned_count: number;
    athlete_count: number;
    assignment_label: string;
}

interface Props {
    team: { name: string; slug: string };
    athlete_count: number;
    programs: ProgramRow[];
}

defineProps<Props>();
</script>

<template>
    <div>
        <Head title="プログラム" />
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-medium text-violet-600">プログラム</p>
                <h1 class="mt-1 text-2xl font-bold">チーム向けプログラム</h1>
                <p class="mt-2 text-sm text-slate-500">
                    在籍選手
                    {{ athlete_count }} 名への割り当て状況を確認します。
                </p>
            </div>
        </div>

        <div class="mt-4">
            <ReadonlyBanner />
        </div>

        <EmptyState
            v-if="programs.length === 0"
            class="mt-6"
            title="公開中のプログラムはありません"
            description="プログラムがまだ登録されていません。"
        />

        <div v-else class="mt-6 grid gap-4">
            <article
                v-for="program in programs"
                :key="program.id"
                class="rounded-xl border bg-white p-5"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">
                            {{ program.title }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ program.starts_on ?? '開始未定' }} 〜
                            {{ program.ends_on ?? '終了未定' }}
                        </p>
                    </div>
                    <span
                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="
                            program.visibility_status === 'published'
                                ? 'bg-emerald-50 text-emerald-700'
                                : program.visibility_status === 'archived'
                                  ? 'bg-slate-100 text-slate-600'
                                  : 'bg-amber-50 text-amber-700'
                        "
                    >
                        {{ program.visibility_label }}
                    </span>
                </div>
                <p v-if="program.summary" class="mt-3 text-sm text-slate-600">
                    {{ program.summary }}
                </p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg bg-slate-50 p-3 text-sm">
                        <p class="font-medium text-slate-800">練習項目</p>
                        <p
                            v-if="program.item_titles.length === 0"
                            class="mt-1 text-slate-500"
                        >
                            項目なし
                        </p>
                        <ul v-else class="mt-1 list-disc space-y-1 pl-5">
                            <li
                                v-for="title in program.item_titles"
                                :key="title"
                            >
                                {{ title }}
                            </li>
                        </ul>
                        <p
                            v-if="
                                program.item_count > program.item_titles.length
                            "
                            class="mt-1 text-slate-500"
                        >
                            他
                            {{
                                program.item_count - program.item_titles.length
                            }}
                            件
                        </p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3 text-sm">
                        <p class="font-medium text-slate-800">割り当て状況</p>
                        <p class="mt-1 text-slate-600">
                            {{ program.assignment_label }}
                        </p>
                    </div>
                </div>
            </article>
        </div>
    </div>
</template>
