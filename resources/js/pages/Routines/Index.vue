<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpen,
    CalendarPlus,
    Check,
    CirclePlay,
    ClipboardList,
    Dumbbell,
    Footprints,
    HeartPulse,
    Music,
    NotebookPen,
    Pencil,
    Plus,
    Sparkles,
    Target,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type { Component } from 'vue';
import PageSectionCard from '@/components/PageSectionCard.vue';
import PageTabShell from '@/components/PageTabShell.vue';
import PageViewTabs from '@/components/PageViewTabs.vue';
import TodayPlanCard from '@/components/routine/TodayPlanCard.vue';
import { Button } from '@/components/ui/button';
import { apiFetch } from '@/lib/apiFetch';
import { activityLogEventTypeLabels } from '@/lib/routineConstants';
import {
    displayDurationMinutes,
    formatMinutesJa,
    latestSession,
    planRunStatus,
    primaryStepPurpose,
} from '@/lib/todayPlanDisplay';
import type {
    ActivityLog,
    Routine,
    RoutineItemCategory,
    RoutinePlan,
    StepPurpose,
} from '@/types/routine';
import type { TodayOps } from '@/types/todayOps';

interface Props {
    date: string;
    tab: 'today' | 'routines' | 'history';
    plans: RoutinePlan[];
    routines: Routine[];
    ops: TodayOps;
    history: ActivityLog[];
}

const props = defineProps<Props>();

const viewTabs = [
    { id: 'today', label: '今日' },
    { id: 'routines', label: 'ルーティン' },
    { id: 'history', label: '履歴' },
];

const activeTab = ref(props.tab);
const applyingId = ref<string | null>(null);
const starting = ref(false);
const showCompleted = ref(false);

watch(
    () => props.tab,
    (tab) => {
        activeTab.value = tab;
    },
);

const completedPlans = computed(() =>
    props.plans.filter((plan) => planRunStatus(plan) === 'completed'),
);

const activePlans = computed(() =>
    props.plans.filter((plan) => planRunStatus(plan) !== 'completed'),
);

const primaryPlan = computed(
    () => activePlans.value[0] ?? props.plans[0] ?? null,
);

const secondaryPlans = computed(() => {
    if (!primaryPlan.value) {
        return [] as RoutinePlan[];
    }

    const rest = props.plans.filter((plan) => plan.id !== primaryPlan.value?.id);

    if (showCompleted.value) {
        return rest;
    }

    return rest.filter((plan) => planRunStatus(plan) !== 'completed');
});

const primaryStatus = computed(() =>
    primaryPlan.value ? planRunStatus(primaryPlan.value) : null,
);

const primarySession = computed(() =>
    primaryPlan.value ? latestSession(primaryPlan.value) : null,
);

const primaryStepCount = computed(
    () => primaryPlan.value?.steps?.length ?? 0,
);

const primaryMinutes = computed(() =>
    primaryPlan.value ? displayDurationMinutes(primaryPlan.value) : null,
);

const programBadge = computed(() => {
    const ctx =
        props.ops.program_context.find(
            (item) => item.plan_id === primaryPlan.value?.id,
        ) ?? props.ops.program_context[0];

    if (!ctx?.week_number && !ctx?.day_code) {
        return null;
    }

    return `W${ctx.week_number ?? '-'} · ${ctx.day_code ?? ''}`;
});

const primaryRecommendation = computed(
    () =>
        props.ops.recommendations.find((card) => card.status === 'pending') ??
        props.ops.recommendations[0] ??
        null,
);

const checkinSummary = computed(() => {
    const checkin = props.ops.checkin;

    if (!checkin) {
        return null;
    }

    if (checkin.fatigue != null) {
        return `チェックイン済 · 疲労 ${checkin.fatigue}/5`;
    }

    return 'チェックイン済';
});

const primaryCtaLabel = computed(() => {
    if (primaryStatus.value === 'in_progress') {
        return 'セッションを続ける';
    }

    if (primaryStatus.value === 'completed') {
        return '結果を見る';
    }

    return 'セッションを開始';
});

const statusLabel = computed(() => {
    if (primaryStatus.value === 'in_progress') {
        return '進行中';
    }

    if (primaryStatus.value === 'completed') {
        return '完了';
    }

    return '準備完了';
});

function categoryIcon(category: RoutineItemCategory | null | undefined): Component {
    switch (category) {
        case 'strength':
            return Dumbbell;
        case 'baseball':
            return Target;
        case 'mobility':
            return Footprints;
        case 'care':
            return HeartPulse;
        case 'music':
            return Music;
        case 'study':
            return BookOpen;
        case 'life':
            return Sparkles;
        default:
            return NotebookPen;
    }
}

function purposeIcon(purpose: StepPurpose | null): Component {
    switch (purpose) {
        case 'strength':
        case 'power':
            return Dumbbell;
        case 'care':
            return HeartPulse;
        case 'practice':
            return Music;
        case 'study':
        case 'review':
            return BookOpen;
        case 'movement':
        case 'prep':
            return Footprints;
        default:
            return NotebookPen;
    }
}

const heroIcon = computed(() => {
    if (!primaryPlan.value) {
        return Dumbbell;
    }

    const category = primaryPlan.value.steps?.[0]?.routine_item?.category;
    if (category) {
        return categoryIcon(category);
    }

    const purpose = primaryStepPurpose(primaryPlan.value);
    if (purpose && purpose !== 'other') {
        return purposeIcon(purpose);
    }

    return Dumbbell;
});

function onTabChange(tab: string): void {
    activeTab.value = tab;
    const query =
        tab === 'today'
            ? {}
            : tab === 'routines'
              ? { tab: 'routines' }
              : { tab: 'history' };

    router.get('/routines', query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function openRoutinesTab(): void {
    onTabChange('routines');
}

async function startOrOpenPrimary(): Promise<void> {
    if (!primaryPlan.value || starting.value) {
        return;
    }

    if (primaryStatus.value === 'in_progress' && primarySession.value) {
        router.visit(`/sessions/${primarySession.value.id}`);

        return;
    }

    if (primaryStatus.value === 'completed') {
        router.visit(`/plans/${primaryPlan.value.id}`);

        return;
    }

    starting.value = true;

    try {
        const result = await apiFetch<{ session: { id: string } }>(
            `/plans/${primaryPlan.value.id}/sessions`,
            { method: 'POST' },
        );
        router.visit(`/sessions/${result.session.id}`);
    } finally {
        starting.value = false;
    }
}

async function applyToToday(routine: Routine): Promise<void> {
    applyingId.value = routine.id;

    try {
        await apiFetch('/plans', {
            method: 'POST',
            body: JSON.stringify({
                title: routine.name,
                scheduled_on: props.date,
                routine_id: routine.id,
            }),
        });

        router.visit('/routines');
    } finally {
        applyingId.value = null;
    }
}

async function deleteRoutine(routine: Routine): Promise<void> {
    if (!confirm(`「${routine.name}」を削除しますか？`)) {
        return;
    }

    await apiFetch(`/routines/${routine.id}`, { method: 'DELETE' });
    router.reload({ only: ['routines'] });
}

function formatOccurredAt(iso: string): string {
    return new Date(iso).toLocaleString('ja-JP', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function historyDescription(log: ActivityLog): string {
    const summary = log.subject_summary;

    if (
        log.event_type === 'matrix_item_completed' &&
        summary?.type === 'matrix_cell_item'
    ) {
        return `「${summary.title}」を完了`;
    }

    if (
        log.event_type === 'matrix_item_reopened' &&
        summary?.type === 'matrix_cell_item'
    ) {
        return `「${summary.title}」を再開`;
    }

    if (log.event_type === 'routine_session_completed') {
        const title =
            summary?.type === 'routine_session' ? summary.plan_title : null;

        return title
            ? `ルーティン実行「${title}」を完了`
            : 'ルーティン実行を完了';
    }

    return activityLogEventTypeLabels[log.event_type];
}
</script>

<template>
    <Head title="ルーティン" />

    <div class="flex h-full flex-1 flex-col rounded-xl p-4 md:px-6 md:pb-6">
        <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-4">
            <PageTabShell
                title="ルーティン"
                subtitle="今日やるセッションを最初に。ルーティンと履歴はここから。"
            >
                <template #actions>
                    <Button
                        type="button"
                        variant="outline"
                        class="shrink-0 font-sans"
                        as-child
                    >
                        <Link href="/programs">
                            <Dumbbell :size="16" :stroke-width="1.6" />
                            プログラム
                        </Link>
                    </Button>
                </template>
                <template #tabs>
                    <PageViewTabs
                        :model-value="activeTab"
                        :tabs="viewTabs"
                        aria-label="ルーティン表示切替"
                        @update:model-value="onTabChange"
                    />
                </template>

                <!-- 今日: メインセッション -->
                <div
                    v-show="activeTab === 'today'"
                    id="panel-today"
                    role="tabpanel"
                    aria-labelledby="tab-today"
                >
                    <div
                        v-if="primaryPlan"
                        class="flex flex-col gap-6 lg:flex-row lg:items-stretch lg:justify-between"
                        aria-label="今日のメインセッション"
                    >
                        <div class="min-w-0 flex-1">
                            <p
                                v-if="programBadge"
                                class="inline-flex rounded-full bg-primary/10 px-3 py-1 font-sans text-xs font-semibold text-primary"
                            >
                                {{ programBadge }}
                            </p>
                            <h2
                                class="mt-3 font-sans text-2xl font-semibold tracking-tight text-cd-ink md:text-3xl"
                            >
                                {{ primaryPlan.title }}
                            </h2>
                            <p class="mt-2 font-sans text-sm text-cd-ink-muted">
                                {{ primaryStepCount }} ステップ
                                <template v-if="primaryMinutes">
                                    · 約{{ formatMinutesJa(primaryMinutes) }}
                                </template>
                            </p>
                            <p
                                class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-cd-moss/15 px-2.5 py-1 font-sans text-xs font-medium text-cd-moss"
                            >
                                <Check :size="12" :stroke-width="2.4" />
                                {{ statusLabel }}
                            </p>

                            <div class="mt-6 flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    class="font-sans tracking-[0.04em]"
                                    :disabled="starting"
                                    @click="startOrOpenPrimary"
                                >
                                    <CirclePlay
                                        :size="16"
                                        :stroke-width="1.7"
                                    />
                                    {{
                                        starting
                                            ? '開始中…'
                                            : primaryCtaLabel
                                    }}
                                    <ArrowRight
                                        :size="16"
                                        :stroke-width="1.6"
                                    />
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="font-sans"
                                    as-child
                                >
                                    <Link :href="`/plans/${primaryPlan.id}`">
                                        プランを見る
                                    </Link>
                                </Button>
                            </div>
                        </div>

                        <div
                            class="flex shrink-0 items-center justify-center lg:w-56"
                            aria-hidden="true"
                        >
                            <div
                                class="flex size-40 items-center justify-center rounded-full bg-primary/8 text-primary"
                            >
                                <component
                                    :is="heroIcon"
                                    :size="72"
                                    :stroke-width="1.2"
                                />
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="flex flex-col items-center gap-4 px-2 py-8 text-center"
                        aria-label="今日のセッションがありません"
                    >
                        <div
                            class="flex size-20 items-center justify-center rounded-full bg-primary/8 text-primary"
                        >
                            <Dumbbell :size="36" :stroke-width="1.4" />
                        </div>
                        <div class="space-y-2">
                            <p
                                class="font-sans text-base font-semibold text-cd-ink"
                            >
                                今日やるセッションはまだありません
                            </p>
                            <p
                                class="max-w-sm font-sans text-sm text-cd-ink-muted"
                            >
                                ルーティンから選ぶか、新しく作って今日に追加できます。
                            </p>
                        </div>
                        <div class="flex flex-wrap justify-center gap-2">
                            <Button type="button" @click="openRoutinesTab">
                                ルーティンから選ぶ
                            </Button>
                            <Button type="button" variant="outline" as-child>
                                <Link href="/routines/create">
                                    ルーティンを作る
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- ルーティン一覧（プライマリ） -->
                <div
                    v-show="activeTab === 'routines'"
                    id="panel-routines"
                    role="tabpanel"
                    aria-labelledby="tab-routines"
                    class="flex flex-col gap-4"
                >
                    <div class="flex justify-end">
                        <Button type="button" as-child>
                            <Link href="/routines/create">
                                <Plus :size="16" :stroke-width="1.8" />
                                ルーティンを作る
                            </Link>
                        </Button>
                    </div>

                    <ul
                        v-if="routines.length > 0"
                        class="flex flex-col"
                        aria-label="ルーティン一覧"
                    >
                        <li
                            v-for="routine in routines"
                            :key="routine.id"
                            class="border-b border-cd-line py-4 first:pt-0 last:border-b-0 last:pb-0"
                            :class="{ 'opacity-55': !routine.is_active }"
                        >
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div
                                    class="flex min-w-0 flex-1 items-center gap-3"
                                >
                                    <div
                                        class="flex size-11 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                                    >
                                        <component
                                            :is="
                                                categoryIcon(
                                                    routine.primary_category,
                                                )
                                            "
                                            :size="20"
                                            :stroke-width="1.6"
                                        />
                                    </div>
                                    <div class="min-w-0">
                                        <Link
                                            :href="`/routines/${routine.id}`"
                                            class="truncate font-sans text-base font-semibold text-cd-ink hover:text-primary"
                                        >
                                            {{ routine.name }}
                                        </Link>
                                        <p
                                            class="mt-0.5 font-sans text-sm text-cd-ink-muted"
                                        >
                                            {{ routine.steps_count ?? 0 }}
                                            ステップ
                                        </p>
                                    </div>
                                </div>

                                <div class="flex shrink-0 flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        as-child
                                    >
                                        <Link
                                            :href="`/routines/${routine.id}`"
                                        >
                                            <Pencil
                                                :size="14"
                                                :stroke-width="1.6"
                                            />
                                            編集
                                        </Link>
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        :disabled="
                                            applyingId === routine.id ||
                                            (routine.steps_count ?? 0) < 1
                                        "
                                        @click="applyToToday(routine)"
                                    >
                                        <CalendarPlus
                                            :size="14"
                                            :stroke-width="1.6"
                                        />
                                        今日に追加
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        :aria-label="`${routine.name} を削除`"
                                        @click="deleteRoutine(routine)"
                                    >
                                        <Trash2
                                            :size="15"
                                            :stroke-width="1.6"
                                        />
                                    </Button>
                                </div>
                            </div>
                        </li>
                    </ul>

                    <div
                        v-else
                        class="flex flex-col items-center gap-4 py-10 text-center"
                    >
                        <div class="space-y-2">
                            <p
                                class="font-sans text-base font-semibold text-cd-ink"
                            >
                                まだルーティンがありません
                            </p>
                            <p
                                class="max-w-sm font-sans text-sm text-cd-ink-muted"
                            >
                                繰り返し使うルーティンを作り、ステップを追加してから今日に追加します。
                            </p>
                        </div>
                        <Button type="button" as-child>
                            <Link href="/routines/create">
                                <Plus :size="16" :stroke-width="1.8" />
                                ルーティンを作る
                            </Link>
                        </Button>
                    </div>
                </div>

                <!-- 履歴（プライマリ） -->
                <div
                    v-show="activeTab === 'history'"
                    id="panel-history"
                    role="tabpanel"
                    aria-labelledby="tab-history"
                    class="flex flex-col gap-4"
                >
                    <ul
                        v-if="history.length > 0"
                        class="flex flex-col"
                        aria-label="最近の履歴"
                    >
                        <li
                            v-for="log in history"
                            :key="log.id"
                            class="border-b border-cd-line py-4 first:pt-0 last:border-b-0 last:pb-0"
                        >
                            <p class="font-sans text-xs text-cd-ink-muted">
                                {{ formatOccurredAt(log.occurred_at) }}
                            </p>
                            <p
                                class="mt-1 font-sans text-sm font-semibold text-cd-ink"
                            >
                                {{ historyDescription(log) }}
                            </p>
                        </li>
                    </ul>
                    <p
                        v-else
                        class="py-10 text-center font-sans text-sm text-cd-ink-muted"
                    >
                        履歴がまだありません。
                    </p>

                    <div class="flex justify-center">
                        <Button type="button" variant="outline" as-child>
                            <Link href="/history">履歴をすべて見る</Link>
                        </Button>
                    </div>
                </div>
            </PageTabShell>

            <!-- 今日（二次ブロック） -->
            <div
                v-show="activeTab === 'today'"
                class="flex flex-col gap-4"
            >
                <div
                    v-if="primaryPlan"
                    class="grid gap-4 md:grid-cols-2"
                >
                    <PageSectionCard padding="sm" aria-label="チェックイン">
                        <div
                            class="flex items-center justify-between gap-3 font-sans text-sm"
                        >
                            <p
                                v-if="checkinSummary"
                                class="inline-flex items-center gap-2 text-cd-moss"
                            >
                                <Check :size="16" :stroke-width="2.2" />
                                {{ checkinSummary }}
                            </p>
                            <p v-else class="text-cd-ink-muted">
                                チェックイン未入力
                            </p>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="font-sans"
                                as-child
                            >
                                <Link :href="`/today?date=${date}`">
                                    {{ checkinSummary ? '変更' : '入力' }}
                                </Link>
                            </Button>
                        </div>
                    </PageSectionCard>

                    <PageSectionCard
                        v-if="primaryRecommendation"
                        padding="sm"
                        aria-label="今日の作戦"
                    >
                        <p
                            class="font-sans text-xs font-medium text-cd-ink-muted"
                        >
                            今日の作戦
                        </p>
                        <p
                            class="mt-1 font-sans text-sm font-semibold text-cd-ink"
                        >
                            {{ primaryRecommendation.title }}
                        </p>
                        <p
                            v-if="primaryRecommendation.rationale"
                            class="mt-1 line-clamp-2 font-sans text-xs text-cd-ink-muted"
                        >
                            {{ primaryRecommendation.rationale }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="font-sans"
                                as-child
                            >
                                <Link :href="`/today?date=${date}`">
                                    <ClipboardList
                                        :size="14"
                                        :stroke-width="1.6"
                                    />
                                    作戦を見る
                                </Link>
                            </Button>
                        </div>
                    </PageSectionCard>
                </div>

                <PageSectionCard
                    v-if="secondaryPlans.length > 0"
                    padding="none"
                    aria-label="その他の今日のセッション"
                >
                    <div
                        class="flex items-center justify-between border-b border-cd-line px-4 py-3"
                    >
                        <p class="font-sans text-sm font-medium text-cd-ink">
                            その他のセッション
                        </p>
                        <Button
                            v-if="completedPlans.length > 0"
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="font-sans"
                            @click="showCompleted = !showCompleted"
                        >
                            {{
                                showCompleted
                                    ? '完了を隠す'
                                    : '完了も表示'
                            }}
                        </Button>
                    </div>
                    <ul class="flex flex-col gap-2 p-3 sm:p-4">
                        <TodayPlanCard
                            v-for="plan in secondaryPlans"
                            :key="plan.id"
                            :plan="plan"
                        />
                    </ul>
                </PageSectionCard>

                <p
                    v-if="primaryPlan"
                    class="px-1 font-sans text-sm text-cd-ink-muted"
                >
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 text-primary underline-offset-2 hover:underline"
                        @click="openRoutinesTab"
                    >
                        <Pencil :size="14" :stroke-width="1.6" />
                        ルーティンを編集
                    </button>
                    <span class="ml-2">テンプレートの追加・編集はこちらから。</span>
                </p>
            </div>
        </div>
    </div>
</template>
