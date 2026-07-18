<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarRange, ChevronRight, Dumbbell } from '@lucide/vue';
import PageSectionCard from '@/components/PageSectionCard.vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import { roadmap, show } from '@/routes/programs';
import type { ProgramSummary } from '@/types/program';

interface Props {
    programs: ProgramSummary[];
}

defineProps<Props>();

const statusLabels: Record<string, string> = {
    draft: '下書き',
    active: '実行中',
    completed: '完了',
    archived: 'アーカイブ',
};
</script>

<template>
    <Head title="プログラム" />

    <div class="flex h-full flex-1 flex-col rounded-xl p-4 md:px-6 md:pb-6">
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-4">
            <PageSectionCard>
                <PageTitleOrnament
                    title="プログラム"
                    subtitle="トレーニングプログラムを実行可能なデータとして管理し、日次プランを生成する上位層です。"
                    align="left"
                />
            </PageSectionCard>

            <PageSectionCard padding="none" aria-label="プログラム一覧">
                <p
                    v-if="programs.length === 0"
                    class="px-5 py-8 text-center font-sans text-sm text-cd-ink-muted"
                >
                    まだプログラムがありません。`php artisan
                    cleardawn:install-program` で登録できます。
                </p>

                <ul v-else class="divide-y divide-cd-line">
                    <li
                        v-for="program in programs"
                        :key="program.id"
                        class="px-5 py-4"
                    >
                        <Link
                            :href="show(program.id)"
                            class="group flex items-center gap-3"
                        >
                            <Dumbbell
                                :size="18"
                                :stroke-width="1.6"
                                class="shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <span
                                class="min-w-0 truncate font-sans text-base font-semibold text-cd-ink group-hover:text-primary"
                            >
                                {{ program.name }}
                            </span>
                            <span
                                class="inline-flex shrink-0 items-center rounded-full bg-cd-moss/15 px-2 py-0.5 font-sans text-xs text-cd-moss"
                            >
                                {{
                                    statusLabels[program.status] ??
                                    program.status
                                }}
                            </span>
                            <ChevronRight
                                :size="16"
                                :stroke-width="1.6"
                                class="ml-auto shrink-0 text-cd-ink-muted"
                                aria-hidden="true"
                            />
                        </Link>

                        <div
                            v-if="program.active_version"
                            class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 pl-8 font-sans text-sm text-cd-ink-muted"
                        >
                            <span>
                                v{{ program.active_version.version_number }} ·
                                {{ program.active_version.starts_on }} 〜
                                {{ program.active_version.ends_on }}
                            </span>
                            <span>
                                全{{ program.active_version.week_count }}週 /
                                週{{ program.active_version.day_count }}DAY
                            </span>
                            <span
                                v-if="
                                    program.active_version.current_week_number
                                "
                                class="font-semibold text-primary"
                            >
                                現在 W{{
                                    program.active_version.current_week_number
                                }}
                            </span>
                            <Link
                                :href="roadmap(program.id)"
                                class="inline-flex items-center gap-1 text-primary hover:underline"
                            >
                                <CalendarRange
                                    :size="14"
                                    :stroke-width="1.6"
                                    aria-hidden="true"
                                />
                                ロードマップ
                            </Link>
                        </div>
                    </li>
                </ul>
            </PageSectionCard>
        </div>
    </div>
</template>
