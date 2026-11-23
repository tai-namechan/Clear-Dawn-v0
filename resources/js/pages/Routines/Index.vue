<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CalendarPlus,
    ChevronDown,
    Dumbbell,
    NotebookPen,
    Plus,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PageTabShell from '@/components/PageTabShell.vue';
import PageViewTabs from '@/components/PageViewTabs.vue';
import TodayPlanCard from '@/components/routine/TodayPlanCard.vue';
import TodayProgressPanel from '@/components/routine/TodayProgressPanel.vue';
import { Button } from '@/components/ui/button';
import { apiFetch } from '@/lib/apiFetch';
import { categoryIcon } from '@/lib/categoryIcon';
import { activityLogEventTypeLabels } from '@/lib/routineConstants';
import { displayDurationMinutes, planRunStatus } from '@/lib/todayPlanDisplay';
import type { ActivityLog, Routine, RoutinePlan } from '@/types/routine';
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
    props.plans.filter(
        (plan) =>
            plan.status !== 'archived' && planRunStatus(plan) !== 'completed',
    ),
);

const displayedPlans = computed(() =>
    props.plans.filter((plan) => plan.status !== 'archived'),
);

const visiblePlans = computed(() => {
    if (showCompleted.value) {
        return [...activePlans.value, ...completedPlans.value];
    }

    return activePlans.value;
});

const completedCount = computed(() => completedPlans.value.length);

const sessionChecklist = computed(() =>
    displayedPlans.value.map((plan) => ({
        id: plan.id,
        title: plan.title,
        done: planRunStatus(plan) === 'completed',
    })),
);
const totalCount = computed(() => displayedPlans.value.length);

const totalMinutes = computed(() =>
    displayedPlans.value.reduce((sum, plan) => {
        return sum + (displayDurationMinutes(plan) ?? 0);
    }, 0),
);

function choiceContextFor(planId: string) {
    return props.ops.program_context.find(
        (context) => context.plan_id === planId,
    );
}

function onTabChange(tab: string): void {
    activeTab.value = tab as 'today' | 'routines' | 'history';
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
        <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-4">
            <PageTabShell
                title="ルーティン"
                subtitle="今日やるセッションを最初に。ルーティンと履歴はここから。"
            >
                <template #hero>
                    <TodayProgressPanel
                        :date="date"
                        :completed-count="completedCount"
                        :total-count="totalCount"
                        :total-minutes="totalMinutes"
                        :sessions="sessionChecklist"
                    />
                </template>
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

                <!-- 今日: セッションの実行に集中 -->
                <div
                    v-show="activeTab === 'today'"
                    id="panel-today"
                    role="tabpanel"
                    aria-labelledby="tab-today"
                    class="flex flex-col gap-4"
                >
                    <div
                        class="grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(16rem,0.8fr)]"
                    >
                        <section
                            class="min-w-0 rounded-2xl border border-cd-line bg-white p-4 shadow-sm md:p-5"
                            aria-label="今日のセッション"
                        >
                            <div
                                class="mb-4 flex flex-wrap items-start justify-between gap-3"
                            >
                                <div>
                                    <h2
                                        class="font-sans text-base font-semibold tracking-tight text-cd-ink"
                                    >
                                        今日のセッション
                                    </h2>
                                    <p
                                        class="mt-1 font-sans text-sm text-cd-ink-muted"
                                    >
                                        プログラム生成プランと手動プランをここから開始します。
                                    </p>
                                </div>
                            </div>

                            <ul
                                v-if="visiblePlans.length > 0"
                                class="flex min-w-0 list-none flex-col gap-3"
                            >
                                <TodayPlanCard
                                    v-for="plan in visiblePlans"
                                    :key="plan.id"
                                    :plan="plan"
                                    :date="date"
                                    :choice-context="choiceContextFor(plan.id)"
                                />
                            </ul>

                            <div
                                v-else
                                class="bg-cd-cream/40 rounded-2xl border border-dashed border-cd-line px-4 py-8 text-center"
                            >
                                <p
                                    class="font-sans text-sm font-medium text-cd-ink"
                                >
                                    今日のセッションはまだありません
                                </p>
                                <p
                                    class="mt-1 font-sans text-sm text-cd-ink-muted"
                                >
                                    ルーティンを追加するか、プログラムからプランを生成してください。
                                </p>
                                <div
                                    class="mt-4 flex flex-wrap justify-center gap-2"
                                >
                                    <Button
                                        type="button"
                                        class="font-sans"
                                        @click="openRoutinesTab"
                                    >
                                        ルーティンを選ぶ
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="font-sans"
                                        as-child
                                    >
                                        <Link href="/programs">
                                            プログラムへ
                                        </Link>
                                    </Button>
                                </div>
                            </div>

                            <Button
                                v-if="visiblePlans.length > 0"
                                type="button"
                                variant="outline"
                                class="mt-3 h-12 w-full rounded-full font-sans font-bold"
                                @click="openRoutinesTab"
                            >
                                <Plus :size="16" :stroke-width="1.8" />
                                ルーティンから追加
                            </Button>

                            <button
                                v-if="completedCount > 0"
                                type="button"
                                class="mt-3 inline-flex items-center gap-1 font-sans text-xs font-medium text-cd-ink-muted transition-colors hover:text-cd-ink"
                                @click="showCompleted = !showCompleted"
                            >
                                <ChevronDown
                                    :size="14"
                                    :stroke-width="1.8"
                                    class="transition-transform"
                                    :class="
                                        showCompleted ? 'rotate-180' : undefined
                                    "
                                />
                                {{
                                    showCompleted
                                        ? '完了を隠す'
                                        : `完了したセッションを表示（${completedCount}）`
                                }}
                            </button>
                        </section>
                    </div>
                </div>

                <!-- ルーティン一覧 -->
                <div
                    v-show="activeTab === 'routines'"
                    id="panel-routines"
                    role="tabpanel"
                    aria-labelledby="tab-routines"
                    class="flex flex-col gap-3"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <p class="font-sans text-sm text-cd-ink-muted">
                            保存したルーティンを今日のプランに追加できます。
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="font-sans"
                                as-child
                            >
                                <Link href="/routine-items">
                                    <NotebookPen
                                        :size="14"
                                        :stroke-width="1.8"
                                    />
                                    種目マスタ
                                </Link>
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                class="font-sans"
                                as-child
                            >
                                <Link href="/routines/create">
                                    <Plus :size="14" :stroke-width="1.8" />
                                    新規作成
                                </Link>
                            </Button>
                        </div>
                    </div>

                    <div
                        v-if="routines.length === 0"
                        class="bg-cd-cream/40 rounded-2xl border border-dashed border-cd-line px-4 py-10 text-center"
                    >
                        <p class="font-sans text-sm font-medium text-cd-ink">
                            ルーティンがまだありません
                        </p>
                        <p class="mt-1 font-sans text-sm text-cd-ink-muted">
                            よくやる流れを保存して、今日のプランにすぐ追加できます。
                        </p>
                        <Button type="button" class="mt-4 font-sans" as-child>
                            <Link href="/routines/create">
                                最初のルーティンを作る
                            </Link>
                        </Button>
                    </div>

                    <article
                        v-for="routine in routines"
                        :key="routine.id"
                        class="rounded-2xl border border-cd-line bg-cd-surface/70 p-4 shadow-sm"
                        :class="{ 'opacity-55': !routine.is_active }"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                                    >
                                        <component
                                            :is="
                                                categoryIcon(
                                                    routine.primary_category,
                                                )
                                            "
                                            :size="18"
                                            :stroke-width="1.6"
                                        />
                                    </span>
                                    <div class="min-w-0">
                                        <h3
                                            class="truncate font-sans text-base font-semibold text-cd-ink"
                                        >
                                            {{ routine.name }}
                                        </h3>
                                        <p
                                            class="mt-0.5 font-sans text-xs text-cd-ink-muted"
                                        >
                                            {{ routine.steps_count ?? 0 }}
                                            ステップ
                                        </p>
                                    </div>
                                </div>
                                <p
                                    v-if="routine.description"
                                    class="mt-2 line-clamp-2 font-sans text-sm text-cd-ink-muted"
                                >
                                    {{ routine.description }}
                                </p>
                            </div>

                            <div class="flex shrink-0 flex-wrap gap-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    class="font-sans"
                                    :disabled="
                                        applyingId === routine.id ||
                                        (routine.steps_count ?? 0) < 1
                                    "
                                    @click="applyToToday(routine)"
                                >
                                    <CalendarPlus
                                        :size="14"
                                        :stroke-width="1.8"
                                    />
                                    {{
                                        applyingId === routine.id
                                            ? '追加中…'
                                            : '今日に追加'
                                    }}
                                </Button>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="warning"
                                    class="font-sans"
                                    as-child
                                >
                                    <Link :href="`/routines/${routine.id}`">
                                        編集
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </article>
                </div>

                <!-- 履歴 -->
                <div
                    v-show="activeTab === 'history'"
                    id="panel-history"
                    role="tabpanel"
                    aria-labelledby="tab-history"
                    class="flex flex-col gap-3"
                >
                    <p class="font-sans text-sm text-cd-ink-muted">
                        最近のルーティン実行・マトリクス完了など。
                    </p>

                    <div
                        v-if="history.length === 0"
                        class="bg-cd-cream/40 rounded-2xl border border-dashed border-cd-line px-4 py-10 text-center"
                    >
                        <p class="font-sans text-sm font-medium text-cd-ink">
                            まだ履歴がありません
                        </p>
                        <p class="mt-1 font-sans text-sm text-cd-ink-muted">
                            セッションを完了するとここに表示されます。
                        </p>
                    </div>

                    <article
                        v-for="log in history"
                        :key="log.id"
                        class="rounded-2xl border border-cd-line bg-cd-surface/70 px-4 py-3"
                    >
                        <div
                            class="flex flex-wrap items-baseline justify-between gap-2"
                        >
                            <p
                                class="font-sans text-sm font-medium text-cd-ink"
                            >
                                {{ historyDescription(log) }}
                            </p>
                            <time
                                class="shrink-0 font-sans text-xs text-cd-ink-muted"
                                :datetime="log.occurred_at"
                            >
                                {{ formatOccurredAt(log.occurred_at) }}
                            </time>
                        </div>
                    </article>
                </div>
            </PageTabShell>
        </div>
    </div>
</template>
