<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import PageSectionCard from '@/components/PageSectionCard.vue';
import RoutinesHubTabs from '@/components/routine/RoutinesHubTabs.vue';
import TodayPlanCard from '@/components/routine/TodayPlanCard.vue';
import TodayProgressPanel from '@/components/routine/TodayProgressPanel.vue';
import { Button } from '@/components/ui/button';
import {
    displayDurationMinutes,
    planRunStatus,
} from '@/lib/todayPlanDisplay';
import type { RoutinePlan } from '@/types/routine';

interface Props {
    date: string;
    plans: RoutinePlan[];
}

const props = defineProps<Props>();

const showCompleted = ref(false);

const completedPlans = computed(() =>
    props.plans.filter((plan) => planRunStatus(plan) === 'completed'),
);

const activePlans = computed(() =>
    props.plans.filter((plan) => planRunStatus(plan) !== 'completed'),
);

const visiblePlans = computed(() => {
    if (showCompleted.value) {
        return [...activePlans.value, ...completedPlans.value];
    }

    return activePlans.value;
});

const completedCount = computed(() => completedPlans.value.length);
const totalCount = computed(() => props.plans.length);

const totalMinutes = computed(() =>
    props.plans.reduce((sum, plan) => {
        return sum + (displayDurationMinutes(plan) ?? 0);
    }, 0),
);
</script>

<template>
    <Head title="今日やる" />

    <div class="flex h-full flex-1 flex-col rounded-xl p-4 md:px-6 md:pb-6">
        <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-4">
            <PageSectionCard padding="sm">
                <RoutinesHubTabs />
            </PageSectionCard>

            <div
                class="flex min-h-0 flex-1 flex-col gap-4 lg:flex-row lg:items-stretch"
            >
                <TodayProgressPanel
                    :date="date"
                    :completed-count="completedCount"
                    :total-count="totalCount"
                    :total-minutes="totalMinutes"
                />

                <section
                    class="flex min-w-0 flex-1 flex-col rounded-2xl border border-cd-line/80 bg-cd-surface/95 px-5 py-5 shadow-sm md:px-6"
                    aria-label="今日のトレーニング・練習"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h1
                                class="font-sans text-lg font-semibold tracking-tight text-cd-ink"
                            >
                                今日のトレーニング・練習
                            </h1>
                            <p
                                class="mt-1 font-sans text-xs text-cd-ink-muted"
                            >
                                開始・再開はここから。作成はルーティン一覧で行います。
                            </p>
                        </div>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="shrink-0 font-sans"
                            as-child
                        >
                            <Link href="/routines">
                                <Plus :size="14" :stroke-width="1.8" />
                                ルーティンを追加
                            </Link>
                        </Button>
                    </div>

                    <ul
                        v-if="visiblePlans.length > 0"
                        class="mt-5 flex flex-col gap-3"
                    >
                        <TodayPlanCard
                            v-for="plan in visiblePlans"
                            :key="plan.id"
                            :plan="plan"
                        />
                    </ul>

                    <div
                        v-else-if="plans.length === 0"
                        class="mt-8 flex flex-1 flex-col items-center justify-center py-10 text-center"
                    >
                        <p class="font-sans text-sm text-cd-ink-muted">
                            この日のルーティンはありません。
                        </p>
                        <p class="mt-2 font-sans text-sm text-cd-ink-muted">
                            <Link
                                href="/routines"
                                class="font-medium text-primary underline-offset-2 hover:underline"
                            >
                                ルーティン
                            </Link>
                            を作ってから「今日やる」に載せてください。
                        </p>
                    </div>

                    <div
                        v-else
                        class="mt-8 flex flex-1 flex-col items-center justify-center py-8 text-center"
                    >
                        <p class="font-sans text-sm text-cd-ink-muted">
                            未完了のルーティンはありません。
                        </p>
                    </div>

                    <button
                        v-if="completedPlans.length > 0"
                        type="button"
                        class="mt-5 inline-flex items-center gap-1.5 self-start font-sans text-sm text-cd-ink-muted transition-colors hover:text-primary"
                        @click="showCompleted = !showCompleted"
                    >
                        <Plus
                            :size="14"
                            :stroke-width="1.8"
                            class="transition-transform"
                            :class="{ 'rotate-45': showCompleted }"
                        />
                        {{
                            showCompleted
                                ? '完了したルーティンを隠す'
                                : '完了したルーティンを表示'
                        }}
                    </button>
                </section>
            </div>
        </div>
    </div>
</template>
