<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    BookOpen,
    Check,
    CirclePlay,
    Clock3,
    Dumbbell,
    EllipsisVertical,
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

    return formatClockRange(session.value?.started_at, session.value?.finished_at);
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

const statusMeta: Record<TodayPlanRunStatus, { label: string; className: string }> = {
    completed: { label: '完了', className: 'bg-cd-moss/15 text-cd-moss' },
    in_progress: { label: '進行中', className: 'bg-cd-sunrise/15 text-cd-sunrise' },
    not_started: { label: '未開始', className: 'bg-muted text-cd-ink-muted' },
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
    <li class="group rounded-xl border border-cd-line/90 bg-white/70 px-3 py-3 transition-colors hover:border-cd-dawn-soft/40 hover:bg-white sm:px-4">
        <div class="flex items-center gap-3">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-cd-dawn-soft/15 text-cd-dawn-soft">
                <component :is="iconComponent" :size="19" :stroke-width="1.6" />
            </div>

            <Link :href="`/plans/${plan.id}`" class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="truncate font-sans text-sm font-semibold text-cd-ink">
                        {{ plan.title }}
                    </p>
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-sans text-[0.68rem] font-medium" :class="statusMeta[status].className">
                        <Check v-if="status === 'completed'" :size="11" :stroke-width="2" />
                        {{ statusMeta[status].label }}
                    </span>
                </div>
                <p class="mt-0.5 line-clamp-1 font-sans text-xs text-cd-ink-muted">
                    {{ description }}
                </p>
            </Link>

            <div v-if="durationMinutes" class="hidden shrink-0 items-center gap-1.5 font-sans text-xs text-cd-ink-muted md:flex">
                <Clock3 :size="14" :stroke-width="1.6" />
                {{ formatMinutesJa(durationMinutes) }}
            </div>

            <Button
                v-if="status === 'not_started'"
                type="button"
                size="sm"
                class="shrink-0"
                :disabled="starting"
                @click="startSession"
            >
                <CirclePlay :size="15" :stroke-width="1.7" />
                {{ starting ? '開始中…' : primaryLabel }}
            </Button>
            <Button v-else type="button" size="sm" class="shrink-0" as-child>
                <Link :href="primaryHref">
                    <CirclePlay :size="15" :stroke-width="1.7" />
                    {{ primaryLabel }}
                </Link>
            </Button>

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button type="button" variant="ghost" size="icon-sm" class="hidden shrink-0 text-cd-ink-muted sm:inline-flex" :aria-label="`${plan.title} のメニュー`">
                        <EllipsisVertical :size="16" :stroke-width="1.6" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="min-w-40">
                    <DropdownMenuItem as-child>
                        <Link :href="`/plans/${plan.id}`">プラン詳細</Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem v-if="status === 'in_progress'" as-child>
                        <Link :href="primaryHref">実行を続ける</Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>

        <div v-if="durationMinutes || clockRange" class="mt-2 flex items-center gap-3 pl-[3.25rem] font-sans text-[11px] text-cd-ink-muted md:hidden">
            <span v-if="durationMinutes">予定 {{ formatMinutesJa(durationMinutes) }}</span>
            <span v-if="clockRange">{{ clockRange }}</span>
        </div>
    </li>
</template>
