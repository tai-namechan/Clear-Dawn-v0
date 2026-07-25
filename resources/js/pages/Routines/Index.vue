<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarPlus, ChevronRight, Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PageSectionCard from '@/components/PageSectionCard.vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import PageViewTabs from '@/components/PageViewTabs.vue';
import RoutinesHubTabs from '@/components/routine/RoutinesHubTabs.vue';
import TodayPlanCard from '@/components/routine/TodayPlanCard.vue';
import { Button } from '@/components/ui/button';
import { apiFetch } from '@/lib/apiFetch';
import {
    displayDurationMinutes,
    planRunStatus,
} from '@/lib/todayPlanDisplay';
import type { Routine, RoutinePlan } from '@/types/routine';

interface Props {
    date: string;
    tab: 'today' | 'menu';
    plans: RoutinePlan[];
    routines: Routine[];
}

const props = defineProps<Props>();

const viewTabs = [
    { id: 'today', label: '今日' },
    { id: 'menu', label: 'メニュー' },
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

function openMenuTab(): void {
    activeTab.value = 'menu';
    router.get(
        '/routines',
        { tab: 'menu' },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function onTabChange(tab: string): void {
    activeTab.value = tab;
    router.get(
        '/routines',
        tab === 'menu' ? { tab: 'menu' } : {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
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
</script>

<template>
    <Head title="ルーティン" />

    <div class="flex h-full flex-1 flex-col rounded-xl p-4 md:px-6 md:pb-6">
        <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-4">
            <PageSectionCard>
                <div class="flex items-start justify-between gap-4">
                    <PageTitleOrnament
                        title="ルーティン"
                        subtitle="今日やるセッションを開始・再開します。メニューは繰り返し使うテンプレです。"
                        align="left"
                    />

                    <Button
                        v-if="activeTab === 'menu'"
                        type="button"
                        class="mt-2 shrink-0"
                        as-child
                    >
                        <Link href="/routines/create">
                            <Plus :size="16" :stroke-width="1.8" />
                            メニューを作る
                        </Link>
                    </Button>
                </div>

                <div class="mt-5">
                    <RoutinesHubTabs />
                </div>

                <div class="mt-4">
                    <PageViewTabs
                        :model-value="activeTab"
                        :tabs="viewTabs"
                        aria-label="ルーティン表示切替"
                        @update:model-value="onTabChange"
                    />
                </div>
            </PageSectionCard>

            <div
                v-show="activeTab === 'today'"
                id="panel-today"
                role="tabpanel"
                aria-labelledby="tab-today"
                class="flex flex-col gap-4"
            >
                <PageSectionCard
                    v-if="totalCount > 0"
                    padding="sm"
                    aria-label="今日の進捗"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 font-sans text-sm text-cd-ink-muted"
                    >
                        <p>
                            <span class="font-semibold text-cd-ink"
                                >{{ completedCount }}</span
                            >
                            /
                            {{ totalCount }} 完了
                            <template v-if="totalMinutes > 0">
                                · 予定
                                {{ totalMinutes }} 分
                            </template>
                        </p>
                        <Button
                            v-if="completedCount > 0"
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
                </PageSectionCard>

                <PageSectionCard
                    v-if="visiblePlans.length > 0"
                    padding="none"
                    aria-label="今日のセッション"
                >
                    <ul class="flex flex-col gap-2 p-3 sm:p-4">
                        <TodayPlanCard
                            v-for="plan in visiblePlans"
                            :key="plan.id"
                            :plan="plan"
                        />
                    </ul>
                </PageSectionCard>

                <PageSectionCard
                    v-else
                    aria-label="今日のセッションがありません"
                >
                    <div
                        class="flex flex-col items-center gap-4 px-2 py-10 text-center"
                    >
                        <div class="space-y-2">
                            <p
                                class="font-sans text-base font-semibold text-cd-ink"
                            >
                                今日やるセッションはまだありません
                            </p>
                            <p
                                class="max-w-sm font-sans text-sm text-cd-ink-muted"
                            >
                                メニューからルーティンを選ぶか、新しく作って今日に追加できます。
                            </p>
                        </div>
                        <div class="flex flex-wrap justify-center gap-2">
                            <Button type="button" @click="openMenuTab">
                                メニューから選ぶ
                            </Button>
                            <Button type="button" variant="outline" as-child>
                                <Link href="/routines/create">
                                    メニューを作る
                                </Link>
                            </Button>
                        </div>
                        <p class="font-sans text-xs text-cd-ink-muted">
                            作戦・チェックインは
                            <Link
                                href="/today"
                                class="underline underline-offset-2 hover:text-primary"
                                >今日/作戦</Link
                            >
                            で確認できます。
                        </p>
                    </div>
                </PageSectionCard>
            </div>

            <div
                v-show="activeTab === 'menu'"
                id="panel-menu"
                role="tabpanel"
                aria-labelledby="tab-menu"
            >
                <PageSectionCard padding="none" aria-label="メニュー一覧">
                    <ul v-if="routines.length > 0" class="flex flex-col">
                        <li
                            v-for="routine in routines"
                            :key="routine.id"
                            class="border-b border-cd-line px-5 py-4 last:border-b-0"
                            :class="{ 'opacity-55': !routine.is_active }"
                        >
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="min-w-0 flex-1">
                                    <Link
                                        :href="`/routines/${routine.id}`"
                                        class="group flex items-center gap-1"
                                    >
                                        <p
                                            class="truncate font-sans text-base font-semibold text-cd-ink group-hover:text-primary"
                                        >
                                            {{ routine.name }}
                                        </p>
                                        <ChevronRight
                                            :size="16"
                                            :stroke-width="1.6"
                                            class="shrink-0 text-cd-ink-muted opacity-100 transition-opacity sm:opacity-0 sm:group-hover:opacity-100"
                                        />
                                    </Link>
                                    <p
                                        v-if="routine.description"
                                        class="mt-1 line-clamp-2 font-sans text-sm text-cd-ink-muted"
                                    >
                                        {{ routine.description }}
                                    </p>
                                    <p
                                        class="mt-1 font-sans text-sm text-cd-ink-muted"
                                    >
                                        {{ routine.steps_count ?? 0 }} ステップ
                                    </p>
                                </div>

                                <div class="flex shrink-0 flex-wrap gap-2">
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
                        class="flex flex-col items-center gap-4 px-5 py-14 text-center"
                    >
                        <div class="space-y-2">
                            <p
                                class="font-sans text-base font-semibold text-cd-ink"
                            >
                                まだメニューがありません
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
                                メニューを作る
                            </Link>
                        </Button>
                    </div>
                </PageSectionCard>
            </div>
        </div>
    </div>
</template>
