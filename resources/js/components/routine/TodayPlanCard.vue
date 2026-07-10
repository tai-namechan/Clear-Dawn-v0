<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BookOpen,
    Check,
    Clock3,
    Dumbbell,
    EllipsisVertical,
    HeartPulse,
    Music,
    NotebookPen,
    Sparkles,
} from '@lucide/vue';
import { computed } from 'vue';
import type { Component } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    displayDurationMinutes,
    formatClockRange,
    formatMinutesJa,
    latestSession,
    planDescription,
    planRunStatus,
    primaryStepPurpose,
} from '@/lib/todayPlanDisplay';
import type { TodayPlanRunStatus } from '@/lib/todayPlanDisplay';
import type { RoutinePlan } from '@/types/routine';

interface Props {
    plan: RoutinePlan;
}

const props = defineProps<Props>();

const status = computed(() => planRunStatus(props.plan));
const session = computed(() => latestSession(props.plan));
const description = computed(() => planDescription(props.plan));
const durationMinutes = computed(() => displayDurationMinutes(props.plan));
const clockRange = computed(() => {
    if (status.value === 'not_started') {
        return null;
    }

    return formatClockRange(
        session.value?.started_at,
        session.value?.finished_at,
    );
});

const primaryHref = computed(() => {
    if (status.value === 'in_progress' && session.value) {
        return `/sessions/${session.value.id}`;
    }

    return `/plans/${props.plan.id}`;
});

const statusMeta: Record<
    TodayPlanRunStatus,
    { label: string; className: string }
> = {
    completed: {
        label: '完了',
        className: 'bg-cd-moss/15 text-cd-moss',
    },
    in_progress: {
        label: '進行中',
        className: 'bg-cd-sunrise/15 text-cd-sunrise',
    },
    not_started: {
        label: '未開始',
        className: 'bg-muted text-cd-ink-muted',
    },
};

const iconComponent = computed((): Component => {
    const purpose = primaryStepPurpose(props.plan);
    const category = props.plan.steps?.[0]?.routine_item?.category;

    if (purpose === 'strength' || purpose === 'power' || category === 'strength') {
        return Dumbbell;
    }

    if (purpose === 'practice' || category === 'music') {
        return Music;
    }

    if (purpose === 'study' || purpose === 'review' || category === 'study') {
        return BookOpen;
    }

    if (purpose === 'care' || category === 'care' || category === 'mobility') {
        return HeartPulse;
    }

    if (purpose === 'prep' || purpose === 'movement') {
        return Sparkles;
    }

    return NotebookPen;
});
</script>

<template>
    <li
        class="group flex items-center gap-3 rounded-xl border border-cd-line/90 bg-white/70 px-4 py-3.5 transition-colors hover:border-cd-dawn-soft/40 hover:bg-white"
    >
        <div
            class="flex size-11 shrink-0 items-center justify-center rounded-full bg-cd-dawn-soft/15 text-cd-dawn-soft"
        >
            <component
                :is="iconComponent"
                :size="20"
                :stroke-width="1.6"
            />
        </div>

        <Link :href="primaryHref" class="min-w-0 flex-1">
            <p class="truncate font-sans text-sm font-semibold text-cd-ink">
                {{ plan.title }}
            </p>
            <p class="mt-0.5 line-clamp-1 font-sans text-xs text-cd-ink-muted">
                {{ description }}
            </p>
        </Link>

        <div
            v-if="durationMinutes"
            class="hidden shrink-0 items-center gap-1.5 font-sans text-xs text-cd-ink-muted sm:flex"
        >
            <Clock3 :size="14" :stroke-width="1.6" />
            {{ formatMinutesJa(durationMinutes) }}
        </div>

        <div class="flex w-[5.5rem] shrink-0 flex-col items-end gap-1">
            <span
                class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 font-sans text-[0.7rem] font-medium"
                :class="statusMeta[status].className"
            >
                <Check
                    v-if="status === 'completed'"
                    :size="12"
                    :stroke-width="2"
                />
                <span
                    v-else-if="status === 'in_progress'"
                    class="inline-flex gap-0.5"
                    aria-hidden="true"
                >
                    <span class="size-1 rounded-full bg-current opacity-90" />
                    <span class="size-1 rounded-full bg-current opacity-70" />
                    <span class="size-1 rounded-full bg-current opacity-50" />
                </span>
                {{ statusMeta[status].label }}
            </span>
            <span
                v-if="clockRange"
                class="font-sans text-[0.65rem] text-cd-ink-muted"
            >
                {{ clockRange }}
            </span>
        </div>

        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    class="shrink-0 text-cd-ink-muted"
                    :aria-label="`${plan.title} のメニュー`"
                >
                    <EllipsisVertical :size="16" :stroke-width="1.6" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="min-w-40">
                <DropdownMenuItem as-child>
                    <Link :href="primaryHref">
                        {{
                            status === 'in_progress'
                                ? '実行を続ける'
                                : status === 'completed'
                                  ? 'プランを見る'
                                  : '編集・開始'
                        }}
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem
                    v-if="status === 'in_progress'"
                    as-child
                >
                    <Link :href="`/plans/${plan.id}`">プラン詳細</Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </li>
</template>
