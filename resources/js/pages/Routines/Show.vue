<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import ReorderControls from '@/components/ReorderControls.vue';
import RoutinesHubTabs from '@/components/routine/RoutinesHubTabs.vue';
import StepEditorDialog, {
    type StepEditorPayload,
} from '@/components/routine/StepEditorDialog.vue';
import { Button } from '@/components/ui/button';
import { apiFetch } from '@/lib/apiFetch';
import { useReorderableList } from '@/composables/useReorderableList';
import { fetchRoutineItemsFromPage } from '@/lib/fetchRoutineItems';
import { purposeChipClasses } from '@/lib/stepPurposeColors';
import {
    estimateStepDurationSeconds,
    formatDurationSeconds,
    formatStepTarget,
    resolveStepPurpose,
    stepPurposeLabels,
    trackingTypeLabels,
} from '@/lib/routineConstants';
import type { RoutineEditor, RoutineItem, RoutineStep } from '@/types/routine';

interface Props {
    routine: RoutineEditor;
}

const props = defineProps<Props>();

const orderedSteps = ref([...(props.routine.steps ?? [])]);

watch(
    () => props.routine.steps,
    (steps) => {
        orderedSteps.value = [...(steps ?? [])];
    },
    { deep: true },
);

const { move } = useReorderableList({
    items: orderedSteps,
    reorderUrl: `/routines/${props.routine.id}/steps/reorder`,
});

const showAddStepModal = ref(false);
const selectedRoutineItemId = ref<string>('');
const stepBlocks = ref('3');
const stepAmount = ref('');
const stepRest = ref('60');
const saving = ref(false);

const routineItems = ref<RoutineItem[]>([]);
const routineItemsLoaded = ref(false);

const totalDurationSeconds = computed(() =>
    (props.routine.steps ?? []).reduce(
        (sum, step) =>
            sum +
            estimateStepDurationSeconds({
                target_blocks: step.target_blocks,
                target_amount: step.target_amount,
                amount_unit: step.amount_unit,
                rest_seconds: step.rest_seconds,
                tracking_type: step.routine_item?.tracking_type,
            }),
        0,
    ),
);

const purposeSummary = computed(() => {
    const counts = new Map<string, number>();

    for (const step of props.routine.steps ?? []) {
        const purpose = resolveStepPurpose(
            step.purpose,
            step.routine_item?.category ?? null,
        );
        counts.set(purpose, (counts.get(purpose) ?? 0) + 1);
    }

    return [...counts.entries()].map(([purpose, count]) => ({
        purpose: purpose as keyof typeof stepPurposeLabels,
        count,
    }));
});

async function loadRoutineItems(): Promise<void> {
    if (routineItemsLoaded.value) {
        return;
    }

    routineItems.value = await fetchRoutineItemsFromPage();
    routineItemsLoaded.value = true;
}

async function openAddStep(): Promise<void> {
    await loadRoutineItems();
    selectedRoutineItemId.value = routineItems.value[0]?.id ?? '';
    stepBlocks.value = '3';
    stepAmount.value = '';
    stepRest.value = '60';
    showAddStepModal.value = true;
}

async function addStep(payload: StepEditorPayload): Promise<void> {
    saving.value = true;

    try {
        await apiFetch(`/routines/${props.routine.id}/steps`, {
            method: 'POST',
            body: JSON.stringify(payload),
        });

        showAddStepModal.value = false;
        router.reload({ only: ['routine'] });
    } finally {
        saving.value = false;
    }
}

async function deleteStep(step: RoutineStep): Promise<void> {
    if (!confirm('このステップを削除しますか？')) {
        return;
    }

    await apiFetch(`/routines/${props.routine.id}/steps/${step.id}`, {
        method: 'DELETE',
    });
    router.reload({ only: ['routine'] });
}

function stepPurpose(step: RoutineStep): string {
    const purpose = resolveStepPurpose(
        step.purpose,
        step.routine_item?.category ?? null,
    );

    return stepPurposeLabels[purpose];
}

function stepPurposeKey(step: RoutineStep) {
    return resolveStepPurpose(
        step.purpose,
        step.routine_item?.category ?? null,
    );
}
</script>

<template>
    <Head :title="routine.name" />

    <div
        class="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4 md:px-6 md:pb-6"
    >
        <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <Link
                        href="/routines"
                        class="mb-3 inline-flex items-center gap-2 font-sans text-sm text-cd-ink-muted transition-colors hover:text-cd-ink"
                    >
                        <ArrowLeft :size="16" :stroke-width="1.6" />
                        ルーティン一覧
                    </Link>
                    <PageTitleOrnament
                        :title="routine.name"
                        :subtitle="routine.description ?? undefined"
                        align="left"
                    />
                </div>

                <Button
                    type="button"
                    class="mt-8 shrink-0 font-sans tracking-[0.08em]"
                    @click="openAddStep"
                >
                    <Plus :size="16" :stroke-width="1.8" />
                    ステップ追加
                </Button>
            </div>

            <RoutinesHubTabs />

            <div class="flex flex-wrap items-center gap-2">
                <span
                    v-for="item in purposeSummary"
                    :key="item.purpose"
                    class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 font-sans text-xs"
                    :class="purposeChipClasses(item.purpose)"
                >
                    {{ stepPurposeLabels[item.purpose] }}
                    <span class="opacity-70">×{{ item.count }}</span>
                </span>
                <span class="ml-auto font-sans text-sm text-cd-ink-muted">
                    合計
                    {{ formatDurationSeconds(totalDurationSeconds) }}
                </span>
            </div>

            <section
                aria-label="ステップ一覧"
                class="cd-shadow-soft overflow-hidden rounded-2xl border border-cd-line bg-cd-surface"
            >
                <div class="overflow-x-auto">
                    <table
                        class="w-full min-w-[640px] text-left font-sans text-sm"
                    >
                        <thead>
                            <tr
                                class="border-b border-cd-line/60 bg-white/40 text-xs tracking-[0.06em] text-cd-ink-muted"
                            >
                                <th class="px-2 py-3 font-medium w-10" />
                                <th class="px-4 py-3 font-medium">#</th>
                                <th class="px-4 py-3 font-medium">実施項目</th>
                                <th class="px-4 py-3 font-medium">目的</th>
                                <th class="px-4 py-3 font-medium">目標</th>
                                <th class="px-4 py-3 font-medium">記録形式</th>
                                <th class="px-4 py-3 font-medium">想定時間</th>
                                <th class="px-4 py-3 font-medium" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(step, index) in orderedSteps"
                                :key="step.id"
                                class="border-b border-cd-line/40 last:border-b-0"
                            >
                                <td class="px-2 py-3">
                                    <ReorderControls
                                        :index="index"
                                        :length="orderedSteps.length"
                                        @up="move(index, -1)"
                                        @down="move(index, 1)"
                                    />
                                </td>
                                <td class="px-4 py-3 text-cd-ink-muted">
                                    {{ index + 1 }}
                                </td>
                                <td
                                    class="px-4 py-3 font-serif tracking-[0.06em] text-cd-ink"
                                >
                                    {{ step.routine_item?.name ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full border px-2 py-0.5 text-xs"
                                        :class="
                                            purposeChipClasses(
                                                stepPurposeKey(step),
                                            )
                                        "
                                    >
                                        {{ stepPurpose(step) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-cd-ink-muted">
                                    {{ formatStepTarget(step) }}
                                </td>
                                <td class="px-4 py-3 text-cd-ink-muted">
                                    {{
                                        step.routine_item
                                            ? trackingTypeLabels[
                                                  step.routine_item
                                                      .tracking_type
                                              ]
                                            : '—'
                                    }}
                                </td>
                                <td class="px-4 py-3 text-cd-ink-muted">
                                    {{
                                        formatDurationSeconds(
                                            estimateStepDurationSeconds({
                                                target_blocks: step.target_blocks,
                                                target_amount: step.target_amount,
                                                amount_unit: step.amount_unit,
                                                rest_seconds: step.rest_seconds,
                                                tracking_type:
                                                    step.routine_item
                                                        ?.tracking_type,
                                            }),
                                        )
                                    }}
                                </td>
                                <td class="px-4 py-3">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon-sm"
                                        aria-label="ステップを削除"
                                        @click="deleteStep(step)"
                                    >
                                        <Trash2
                                            :size="14"
                                            :stroke-width="1.6"
                                        />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="!routine.steps?.length"
                    class="px-5 py-12 text-center font-sans text-sm text-cd-ink-muted"
                >
                    <p>ステップがまだありません。</p>
                    <p class="mt-2">
                        実施項目を追加してルーティンを組み立てましょう。
                    </p>
                </div>
            </section>
        </div>
    </div>

    <StepEditorDialog
        v-model:open="showAddStepModal"
        v-model:selected-routine-item-id="selectedRoutineItemId"
        v-model:blocks="stepBlocks"
        v-model:amount="stepAmount"
        v-model:rest-seconds="stepRest"
        :routine-items="routineItems"
        :saving="saving"
        @submit="addStep"
    />
</template>
