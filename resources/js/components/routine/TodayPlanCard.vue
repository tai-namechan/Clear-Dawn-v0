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
import { IconBarbell, IconBed, IconRun, IconYoga } from '@tabler/icons-vue';
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
import { categoryIcon } from '@/lib/categoryIcon';
import { planRunStatusBadgeClasses } from '@/lib/statusBadge';
import {
    displayDurationMinutes,
    dominantItemCategory,
    formatClockRange,
    formatMinutesJa,
    latestSession,
    planDescription,
    planRunStatus,
    primaryStepPurpose,
} from '@/lib/todayPlanDisplay';
import type { TodayPlanRunStatus } from '@/lib/todayPlanDisplay';
import type { RoutinePlan } from '@/types/routine';
import type { TodayOpsProgramContext } from '@/types/todayOps';

interface Props {
    plan: RoutinePlan;
    date: string;
    choiceContext?: TodayOpsProgramContext;
}

const props = defineProps<Props>();
const starting = ref(false);
const selectingChoiceId = ref<string | null>(null);
const status = computed(() => planRunStatus(props.plan));
const needsChoice = computed(() => props.choiceContext?.needs_choice === true);
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
    const category = dominantItemCategory(props.plan);

    if (category !== null) {
        return categoryIcon(category);
    }

    // ステップが無いプラン（選択待ちなど）は目的から推測する
    const purpose = primaryStepPurpose(props.plan);

    if (purpose === 'strength' || purpose === 'power') {
        return Dumbbell;
    }

    if (purpose === 'practice') {
        return Music;
    }

    if (purpose === 'study' || purpose === 'review') {
        return BookOpen;
    }

    if (purpose === 'care') {
        return HeartPulse;
    }

    if (purpose === 'prep' || purpose === 'movement') {
        return Sparkles;
    }

    return NotebookPen;
});

function choiceIcon(label: string): Component {
    if (label.includes('ヨガ')) {
        return IconYoga;
    }

    if (label.includes('ロードワーク')) {
        return IconRun;
    }

    if (label.includes('休養')) {
        return IconBed;
    }

    return IconBarbell;
}

async function startSession(): Promise<void> {
    if (starting.value || needsChoice.value || status.value !== 'not_started') {
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

async function selectChoice(choiceOptionId: string): Promise<void> {
    if (selectingChoiceId.value !== null) {
        return;
    }

    selectingChoiceId.value = choiceOptionId;

    try {
        await apiFetch('/today/program-choice', {
            method: 'POST',
            body: JSON.stringify({
                date: props.date,
                choice_option_id: choiceOptionId,
            }),
        });
        await router.reload({ only: ['ops', 'plans'] });
    } finally {
        selectingChoiceId.value = null;
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
                    v-if="status === 'not_started' && !needsChoice"
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

        <div
            v-if="needsChoice && choiceContext"
            class="mt-3 border-t border-cd-line pt-3 sm:ml-14"
        >
            <p class="font-sans text-xs font-medium text-cd-ink">
                今日行う内容を選択してください
            </p>
            <div class="mt-2 flex flex-wrap gap-2">
                <Button
                    v-for="option in choiceContext.choice_options"
                    :key="option.id"
                    type="button"
                    size="sm"
                    :variant="
                        option.estimated_minutes === 0 ? 'ghost' : 'outline'
                    "
                    class="font-sans"
                    :disabled="selectingChoiceId !== null"
                    @click="selectChoice(option.id)"
                >
                    <component
                        :is="choiceIcon(option.label)"
                        :size="16"
                        :stroke-width="1.7"
                    />
                    {{
                        selectingChoiceId === option.id
                            ? '選択中…'
                            : option.label
                    }}
                    <span
                        v-if="option.estimated_minutes"
                        class="text-cd-ink-muted"
                    >
                        {{ option.estimated_minutes }}分
                    </span>
                </Button>
            </div>
        </div>
    </li>
</template>
