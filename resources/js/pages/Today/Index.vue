<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CirclePlay } from '@lucide/vue';
import DateNavigator from '@/components/DateNavigator.vue';
import PageSectionCard from '@/components/PageSectionCard.vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import RoutinesHubTabs from '@/components/routine/RoutinesHubTabs.vue';
import { routinePlanStatusLabels } from '@/lib/routineConstants';
import type { RoutinePlan } from '@/types/routine';

interface Props {
    date: string;
    plans: RoutinePlan[];
}

defineProps<Props>();

function latestSession(plan: RoutinePlan) {
    return plan.sessions?.[0] ?? null;
}
</script>

<template>
    <Head title="今日やる" />

    <div
        class="flex h-full flex-1 flex-col rounded-xl p-4 md:px-6 md:pb-6"
    >
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-4">
            <PageSectionCard>
                <PageTitleOrnament
                    title="今日やる"
                    subtitle="今日のルーティンを開始・再開します。作成は「ルーティン」タブで行います。"
                    align="left"
                />
                <div class="mt-5">
                    <RoutinesHubTabs />
                </div>
            </PageSectionCard>

            <PageSectionCard padding="sm">
                <DateNavigator
                    :date="date"
                    route-url="/today"
                    :reload-only="['plans', 'date']"
                />
            </PageSectionCard>

            <PageSectionCard padding="none" aria-label="プラン一覧">
                <ul v-if="plans.length > 0" class="flex flex-col">
                    <li
                        v-for="plan in plans"
                        :key="plan.id"
                        class="border-b border-cd-line px-5 py-4 last:border-b-0"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <Link
                                    :href="`/plans/${plan.id}`"
                                    class="font-sans text-base font-semibold text-cd-ink hover:text-primary"
                                >
                                    {{ plan.title }}
                                </Link>
                                <p
                                    class="mt-1 font-sans text-sm text-cd-ink-muted"
                                >
                                    {{ plan.steps?.length ?? 0 }} ステップ
                                    <span
                                        v-if="plan.life_area"
                                        class="before:mx-1.5 before:content-['·']"
                                    >
                                        {{ plan.life_area.name }}
                                    </span>
                                </p>
                            </div>

                            <span
                                class="inline-flex shrink-0 rounded-full px-2.5 py-0.5 font-sans text-xs font-medium"
                                :class="
                                    latestSession(plan)?.status === 'completed'
                                        ? 'bg-cd-moss/15 text-cd-moss'
                                        : latestSession(plan)?.status ===
                                            'in_progress'
                                          ? 'bg-cd-sunrise/15 text-cd-sunrise'
                                          : plan.status === 'ready'
                                            ? 'bg-primary/10 text-primary'
                                            : 'bg-muted text-cd-ink-muted'
                                "
                            >
                                {{
                                    latestSession(plan)?.status === 'in_progress'
                                        ? '実行中'
                                        : latestSession(plan)?.status ===
                                            'completed'
                                          ? '完了済み'
                                          : routinePlanStatusLabels[plan.status]
                                }}
                            </span>
                        </div>

                        <div class="mt-3">
                            <Link
                                v-if="
                                    latestSession(plan)?.status === 'in_progress'
                                "
                                :href="`/sessions/${latestSession(plan)!.id}`"
                                class="inline-flex items-center gap-1.5 rounded-full border border-primary/30 bg-primary/10 px-3 py-1 font-sans text-xs font-medium text-primary transition-colors hover:bg-primary-hover hover:text-primary"
                            >
                                <CirclePlay :size="14" :stroke-width="1.6" />
                                続ける
                            </Link>
                            <Link
                                v-else
                                :href="`/plans/${plan.id}`"
                                class="inline-flex items-center gap-1.5 rounded-full border border-cd-line px-3 py-1 font-sans text-xs font-medium text-cd-ink-muted transition-colors hover:border-primary/30 hover:bg-primary-hover hover:text-primary"
                            >
                                編集・開始
                            </Link>
                        </div>
                    </li>
                </ul>

                <div
                    v-else
                    class="px-5 py-12 text-center font-sans text-sm text-cd-ink-muted"
                >
                    <p>この日のルーティンはありません。</p>
                    <p class="mt-2">
                        <Link
                            href="/routines"
                            class="font-medium text-primary underline-offset-2 hover:underline"
                        >
                            ルーティン
                        </Link>
                        を作ってから「今日やる」に載せてください。
                    </p>
                </div>
            </PageSectionCard>
        </div>
    </div>
</template>
