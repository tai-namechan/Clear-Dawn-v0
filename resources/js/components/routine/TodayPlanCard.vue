<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    BookOpen,
    Check,
    CirclePlay,
    Clock3,
    EllipsisVertical,
    Footprints,
    HeartPulse,
    Music,
    NotebookPen,
    Sparkles,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import type { Component } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { apiFetch } from '@/lib/apiFetch';
import { planRunStatusBadgeClasses } from '@/lib/statusBadge';
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
const starting = ref(false);
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

const primaryLabel = computed(() => {
    if (status.value === 'in_progress') {
        return '続ける';
    }

    if (status.value === 'completed') {
        return '結果';
    }

    return '開始';
});

const statusMeta: Record<
    TodayPlanRunStatus,
    { label: string; className: string }
> = {
    completed: {
        label: '完了',
        className: planRunStatusBadgeClasses.completed,
    },
    in_progress: {
        label: '進行中',
        className: planRunStatusBadgeClasses.in_progress,
    },
    not_started: {
        label: '未開始',
        className: planRunStatusBadgeClasses.not_started,
    },
};

const iconComponent = computed((): Component => {
    const purpose = primaryStepPurpose(props.plan);
    const category = props.plan.steps?.[0]?.routine_item?.category;

    if (
        purpose === 'strength' ||
        purpose === 'power' ||
        category === 'strength'
    ) {
        return Footprints;
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

async function startSession(): Promise<void> {
    if (starting.value || status.value !== 'not_started') {
        return;
    }

    starting.value = true;

    try {
        const result = await apiFetch<{ session: { id: string } }>(
            `/plans/${props.plan.id}/sessions`,
            { method: 'POST' },
        );
        router.visit(`/sessions/${result.session.id}`);
    } finally {
        starting.value = false;
    }
}
</script>

<template>
    <li
        class="group min-w-0 rounded-2xl border border-cd-line bg-white px-3 py-3 sm:px-4"
    >
        <div class="flex min-w-0 items-center gap-2.5 sm:gap-3">
            <div
                class="flex size-11 shrink-0 items-center justify-center rounded-full bg-[#F3F1F8] text-primary"
            >
                <component :is="iconComponent" :size="20" :stroke-width="1.6" />
            </div>

            <div class="min-w-0 flex-1 overflow-hidden">
                <Link :href="`/plans/${plan.id}`" class="block min-w-0">
                    <div class="flex min-w-0 flex-wrap items-center gap-2">
                        <p
                            class="min-w-0 truncate font-sans text-sm font-semibold text-cd-ink"
                        >
                            {{ plan.title }}
                        </p>
                        <span
                            class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 font-sans text-[0.68rem] font-medium"
                            :class="statusMeta[status].className"
                        >
                            <Check
                                v-if="status === 'completed'"
                                :size="11"
                                :stroke-width="2"
                            />
                            {{ statusMeta[status].label }}
                        </span>
                    </div>
                    <p
                        class="mt-0.5 line-clamp-1 font-sans text-xs text-cd-ink-muted"
                    >
                        {{ description }}
                    </p>
                </Link>
                <div
                    v-if="durationMinutes || clockRange"
                    class="mt-1.5 flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1 font-sans text-[11px] text-cd-ink-muted md:hidden"
                >
                    <span v-if="durationMinutes"
                        >予定 {{ formatMinutesJa(durationMinutes) }}</span
                    >
                    <span v-if="clockRange">{{ clockRange }}</span>
                </div>
            </div>

            <div
                class="flex shrink-0 items-center gap-1.5 self-center sm:gap-2"
            >
                <div
                    v-if="durationMinutes"
                    class="hidden items-center gap-1.5 font-sans text-xs text-cd-ink-muted md:flex"
                >
                    <Clock3 :size="14" :stroke-width="1.6" />
                    {{ formatMinutesJa(durationMinutes) }}
                </div>

                <Button
                    v-if="status === 'not_started'"
                    type="button"
                    size="sm"
                    class="rounded-full px-3.5 font-sans"
                    :disabled="starting"
                    @click="startSession"
                >
                    <CirclePlay :size="15" :stroke-width="1.7" />
                    {{ starting ? '開始中…' : primaryLabel }}
                </Button>
                <Button
                    v-else
                    type="button"
                    size="sm"
                    class="rounded-full px-3.5 font-sans"
                    as-child
                >
                    <Link :href="primaryHref">
                        <CirclePlay :size="15" :stroke-width="1.7" />
                        {{ primaryLabel }}
                    </Link>
                </Button>

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            class="text-cd-ink-muted sm:inline-flex"
                            :aria-label="`${plan.title} のメニュー`"
                        >
                            <EllipsisVertical :size="16" :stroke-width="1.6" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="min-w-40">
                        <DropdownMenuItem as-child>
                            <Link :href="`/plans/${plan.id}`">プラン詳細</Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            v-if="status === 'in_progress'"
                            as-child
                        >
                            <Link :href="primaryHref">実行を続ける</Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    </li>
</template>
