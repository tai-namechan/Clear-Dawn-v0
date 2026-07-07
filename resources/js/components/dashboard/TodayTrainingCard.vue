<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, CirclePlay } from '@lucide/vue';
import { computed } from 'vue';
import { trainingPlanStatusLabels } from '@/lib/trainingConstants';
import type { TodayTraining, TrainingPlan } from '@/types/training';

interface Props {
    todayTraining: TodayTraining;
}

const props = defineProps<Props>();

const plans = computed(() => props.todayTraining.plans ?? []);

function latestRun(plan: TrainingPlan) {
    return plan.runs?.[0] ?? null;
}

function planStatusLabel(plan: TrainingPlan): string {
    const run = latestRun(plan);

    if (run?.status === 'in_progress') {
        return '実行中';
    }

    if (run?.status === 'completed') {
        return '完了済み';
    }

    return trainingPlanStatusLabels[plan.status];
}
</script>

<template>
    <section
        aria-label="今日のトレーニング"
        class="cd-shadow-soft rounded-2xl border border-cd-line bg-cd-surface"
    >
        <div
            class="flex items-center justify-between gap-3 border-b border-cd-line/60 px-5 py-4"
        >
            <h2
                class="font-serif text-base tracking-[0.12em] text-cd-ink"
            >
                今日のメニュー
            </h2>
            <Link
                href="/training"
                class="flex items-center gap-1 font-sans text-xs tracking-[0.08em] text-cd-ink-muted transition-colors hover:text-cd-ink"
            >
                すべて見る
                <ChevronRight :size="14" :stroke-width="1.6" />
            </Link>
        </div>

        <ul v-if="plans.length > 0" class="flex flex-col">
            <li
                v-for="plan in plans"
                :key="plan.id"
                class="border-b border-cd-line/60 px-5 py-4 last:border-b-0"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p
                            class="truncate font-serif text-base tracking-[0.08em] text-cd-ink"
                        >
                            {{ plan.title }}
                        </p>
                        <p class="mt-1 font-sans text-xs text-cd-ink-muted">
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
                        class="inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 font-sans text-xs"
                        :class="
                            latestRun(plan)?.status === 'completed'
                                ? 'bg-cd-moss/15 text-cd-moss'
                                : latestRun(plan)?.status === 'in_progress'
                                  ? 'bg-cd-sunrise/15 text-cd-sunrise'
                                  : plan.status === 'ready'
                                    ? 'bg-primary/10 text-primary'
                                    : 'bg-muted text-cd-ink-muted'
                        "
                    >
                        {{ planStatusLabel(plan) }}
                    </span>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <Link
                        v-if="latestRun(plan)?.status === 'in_progress'"
                        :href="`/training/runs/${latestRun(plan)!.id}`"
                        class="inline-flex items-center gap-1.5 rounded-full border border-primary/30 bg-primary/10 px-3 py-1 font-sans text-xs tracking-[0.06em] text-primary transition-colors hover:bg-primary/15"
                    >
                        <CirclePlay :size="14" :stroke-width="1.6" />
                        続ける
                    </Link>
                    <Link
                        v-else
                        :href="`/training/plans/${plan.id}`"
                        class="inline-flex items-center gap-1.5 rounded-full border border-cd-line/80 bg-white/60 px-3 py-1 font-sans text-xs tracking-[0.06em] text-cd-ink-muted transition-colors hover:border-cd-line hover:text-cd-ink"
                    >
                        詳細を見る
                    </Link>
                </div>
            </li>
        </ul>

        <p
            v-else
            class="px-5 py-8 text-center font-sans text-sm text-cd-ink-muted"
        >
            今日のメニューはまだありません。
            <Link
                href="/training"
                class="mt-1 block text-primary underline-offset-2 hover:underline"
            >
                メニューを追加する
            </Link>
        </p>
    </section>
</template>
