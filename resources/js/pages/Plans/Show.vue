<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, CirclePlay, Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PageSectionCard from '@/components/PageSectionCard.vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import ReorderableList from '@/components/ReorderableList.vue';
import StepEditorDialog, {
    type StepEditorPayload,
} from '@/components/routine/StepEditorDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { apiFetch, ApiError } from '@/lib/apiFetch';
import { ensureArray } from '@/lib/array';
import { fetchRoutineItemsFromPage } from '@/lib/fetchRoutineItems';
import { purposeChipClasses } from '@/lib/stepPurposeColors';
import {
    formatStepTarget,
    resolveStepPurpose,
    routinePlanStatusLabels,
    stepPurposeLabels,
    trackingTypeLabels,
} from '@/lib/routineConstants';
import type {
    RoutineItem,
    RoutinePlan,
    RoutinePlanStep,
    Video,
} from '@/types/routine';

interface Props {
    plan: RoutinePlan;
    routineItems?: RoutineItem[];
    videos?: Video[];
}

const props = withDefaults(defineProps<Props>(), {
    routineItems: () => [],
    videos: () => [],
});

const title = ref(props.plan.title);
const note = ref(props.plan.note ?? '');
const saving = ref(false);
const starting = ref(false);
const showAddStepModal = ref(false);
const routineItems = ref<RoutineItem[]>([...props.routineItems]);
const stepEditorRef = ref<{
    applyApiErrors: (error: unknown) => void;
    clearFieldErrors: () => void;
} | null>(null);

watch(
    () => props.routineItems,
    (items) => {
        routineItems.value = [...items];
    },
);

const steps = computed(() => ensureArray(props.plan.steps));

const canStart = computed(
    () =>
        props.plan.status === 'ready' &&
        steps.value.length > 0 &&
        !activeSession.value,
);

const activeSession = computed(
    () =>
        props.plan.sessions?.find(
            (session) => session.status === 'in_progress',
        ) ?? null,
);

async function savePlan(): Promise<void> {
    saving.value = true;

    try {
        await apiFetch(`/plans/${props.plan.id}`, {
            method: 'PATCH',
            body: JSON.stringify({
                title: title.value.trim(),
                note: note.value.trim() || null,
            }),
        });

        router.reload({ only: ['plan'] });
    } finally {
        saving.value = false;
    }
}

async function startSession(): Promise<void> {
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

async function loadRoutineItems(): Promise<void> {
    try {
        routineItems.value = await fetchRoutineItemsFromPage();
    } catch {
        // Keep current list if auxiliary fetch fails.
    }
}

async function openAddStep(): Promise<void> {
    if (routineItems.value.length === 0) {
        await loadRoutineItems();
    } else {
        void loadRoutineItems();
    }

    showAddStepModal.value = true;
}

async function addStep(payload: StepEditorPayload): Promise<void> {
    saving.value = true;
    stepEditorRef.value?.clearFieldErrors();

    try {
        await apiFetch(`/plans/${props.plan.id}/steps`, {
            method: 'POST',
            body: JSON.stringify(payload),
        });

        showAddStepModal.value = false;
        router.reload({ only: ['plan', 'routineItems'] });
    } catch (error) {
        stepEditorRef.value?.applyApiErrors(error);

        if (!(error instanceof ApiError) || error.status >= 500) {
            console.error(error);
        }
    } finally {
        saving.value = false;
    }
}

async function deleteStep(step: RoutinePlanStep): Promise<void> {
    if (!confirm('このステップを削除しますか？')) {
        return;
    }

    await apiFetch(`/plans/${props.plan.id}/steps/${step.id}`, {
        method: 'DELETE',
    });
    router.reload({ only: ['plan'] });
}

async function deletePlan(): Promise<void> {
    if (!confirm('この実行プランを削除しますか？')) {
        return;
    }

    await apiFetch(`/plans/${props.plan.id}`, {
        method: 'DELETE',
    });
    router.visit('/today');
}

function stepPurposeKey(step: RoutinePlanStep) {
    return resolveStepPurpose(
        step.purpose,
        step.routine_item?.category ?? null,
    );
}
</script>

<template>
    <Head :title="plan.title" />

    <div
        class="flex h-full flex-1 flex-col rounded-xl p-4 md:px-6 md:pb-6"
    >
        <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-4">
            <PageSectionCard>
                <div class="flex flex-col gap-4">
                    <Link
                        href="/today"
                        class="inline-flex items-center gap-2 font-sans text-sm font-medium text-cd-ink-muted transition-colors hover:text-primary"
                    >
                        <ArrowLeft :size="16" :stroke-width="1.6" />
                        今日の実行プラン
                    </Link>

                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <PageTitleOrnament
                            :title="plan.title"
                            :subtitle="`${plan.scheduled_on} · ${routinePlanStatusLabels[plan.status]}`"
                            align="left"
                        />

                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-if="activeSession"
                                type="button"
                                @click="
                                    router.visit(
                                        `/sessions/${activeSession.id}`,
                                    )
                                "
                            >
                                <CirclePlay :size="16" :stroke-width="1.6" />
                                実行を続ける
                            </Button>
                            <Button
                                v-else-if="canStart"
                                type="button"
                                :disabled="starting"
                                @click="startSession"
                            >
                                <CirclePlay :size="16" :stroke-width="1.6" />
                                開始
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="実行プランを削除"
                                @click="deletePlan"
                            >
                                <Trash2 :size="16" :stroke-width="1.6" />
                            </Button>
                        </div>
                    </div>
                </div>
            </PageSectionCard>

            <PageSectionCard aria-label="基本情報">
                <div class="flex flex-col gap-3">
                    <Input
                        v-model="title"
                        placeholder="プラン名"
                        maxlength="100"
                    />
                    <Input v-model="note" placeholder="メモ（任意）" />
                    <div class="flex justify-end">
                        <Button
                            type="button"
                            size="sm"
                            :disabled="saving"
                            @click="savePlan"
                        >
                            保存
                        </Button>
                    </div>
                </div>
            </PageSectionCard>

            <PageSectionCard padding="none" aria-label="ステップ一覧">
                <div
                    class="flex items-center justify-between gap-3 border-b border-cd-line px-5 py-4"
                >
                    <h2 class="font-sans text-base font-semibold text-cd-ink">
                        ステップ
                    </h2>
                    <Button type="button" size="sm" @click="openAddStep">
                        <Plus :size="14" :stroke-width="1.8" />
                        追加
                    </Button>
                </div>

                <ReorderableList
                    v-if="steps.length"
                    :items="steps"
                    :reorder-url="`/plans/${plan.id}/steps/reorder`"
                    :item-label="(step) => step.routine_item?.name"
                >
                    <template #row="{ item: step, index }">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-sans text-xs text-cd-ink-muted">
                                {{ index + 1 }}
                            </span>
                            <span
                                class="font-sans text-base font-semibold text-cd-ink"
                            >
                                {{ step.routine_item?.name ?? '—' }}
                            </span>
                            <span
                                class="inline-flex rounded-full border px-2 py-0.5 font-sans text-xs"
                                :class="
                                    purposeChipClasses(stepPurposeKey(step))
                                "
                            >
                                {{ stepPurposeLabels[stepPurposeKey(step)] }}
                            </span>
                        </div>
                        <p class="mt-1 font-sans text-xs text-cd-ink-muted">
                            {{ formatStepTarget(step) }}
                            <span class="before:mx-1.5 before:content-['·']">
                                {{
                                    step.routine_item
                                        ? trackingTypeLabels[
                                              step.routine_item.tracking_type
                                          ]
                                        : ''
                                }}
                            </span>
                        </p>
                    </template>
                    <template #actions="{ item: step }">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            aria-label="ステップを削除"
                            @click="deleteStep(step)"
                        >
                            <Trash2 :size="14" :stroke-width="1.6" />
                        </Button>
                    </template>
                </ReorderableList>

                <div
                    v-else
                    class="px-5 py-12 text-center font-sans text-sm text-cd-ink-muted"
                >
                    <p>ステップがまだありません。</p>
                    <p class="mt-2">
                        ステップを追加して実行プランを組み立てましょう。
                    </p>
                </div>
            </PageSectionCard>
        </div>
    </div>

    <StepEditorDialog
        ref="stepEditorRef"
        v-model:open="showAddStepModal"
        :routine-items="routineItems"
        :videos="videos"
        :saving="saving"
        @submit="addStep"
        @items-changed="loadRoutineItems"
    />
</template>
