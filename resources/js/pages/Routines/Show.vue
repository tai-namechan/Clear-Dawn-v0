<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import ReorderableList from '@/components/ReorderableList.vue';
import ExercisePickerDialog from '@/components/training/ExercisePickerDialog.vue';
import RoutinesHubTabs from '@/components/training/RoutinesHubTabs.vue';
import { Button } from '@/components/ui/button';
import { apiFetch } from '@/lib/apiFetch';
import { ensureArray } from '@/lib/array';
import { useFetchExercises } from '@/lib/fetchExercises';
import { purposeChipClasses } from '@/lib/stepPurposeColors';
import {
    estimateStepDurationSeconds,
    formatDurationSeconds,
    resolveStepPurpose,
    stepPurposeLabels,
    trackingTypeLabels,
} from '@/lib/trainingConstants';
import type { Exercise, RoutineEditor, RoutineStep } from '@/types/training';

interface Props {
    routine: RoutineEditor;
}

const props = defineProps<Props>();

const { fetchExercises } = useFetchExercises();

const showAddStepModal = ref(false);
const selectedExerciseId = ref<string>('');
const stepSets = ref('3');
const stepReps = ref('');
const stepRest = ref('60');
const saving = ref(false);

const exercises = ref<Exercise[]>([]);
const exercisesLoaded = ref(false);

const steps = computed(() => ensureArray(props.routine.steps));

const totalDurationSeconds = computed(() =>
    steps.value.reduce(
        (sum, step) =>
            sum +
            estimateStepDurationSeconds({
                target_sets: step.target_sets,
                target_duration_seconds: step.target_duration_seconds,
                rest_seconds: step.rest_seconds,
                tracking_type: step.exercise?.tracking_type,
            }),
        0,
    ),
);

const purposeSummary = computed(() => {
    const counts = new Map<string, number>();

    for (const step of steps.value) {
        const purpose = resolveStepPurpose(
            step.purpose,
            step.exercise?.category ?? null,
        );
        counts.set(purpose, (counts.get(purpose) ?? 0) + 1);
    }

    return [...counts.entries()].map(([purpose, count]) => ({
        purpose: purpose as keyof typeof stepPurposeLabels,
        count,
    }));
});

async function loadExercises(): Promise<void> {
    if (exercisesLoaded.value) {
        return;
    }

    exercises.value = await fetchExercises();
    exercisesLoaded.value = true;
}

async function openAddStep(): Promise<void> {
    await loadExercises();
    selectedExerciseId.value = exercises.value[0]?.id ?? '';
    stepSets.value = '3';
    stepReps.value = '';
    stepRest.value = '60';
    showAddStepModal.value = true;
}

async function addStep(): Promise<void> {
    if (!selectedExerciseId.value) {
        return;
    }

    saving.value = true;

    try {
        await apiFetch(`/routines/${props.routine.id}/steps`, {
            method: 'POST',
            body: JSON.stringify({
                exercise_id: selectedExerciseId.value,
                target_sets: stepSets.value ? Number(stepSets.value) : null,
                target_reps: stepReps.value ? Number(stepReps.value) : null,
                rest_seconds: stepRest.value ? Number(stepRest.value) : null,
            }),
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
        step.exercise?.category ?? null,
    );

    return stepPurposeLabels[purpose];
}

function stepPurposeKey(step: RoutineStep) {
    return resolveStepPurpose(step.purpose, step.exercise?.category ?? null);
}

function formatStepTarget(step: RoutineStep): string {
    const parts: string[] = [];

    if (step.target_sets) {
        parts.push(`${step.target_sets}セット`);
    }

    if (step.target_reps) {
        parts.push(`${step.target_reps}回`);
    }

    if (step.target_weight_kg) {
        parts.push(`${step.target_weight_kg}kg`);
    }

    if (step.target_duration_seconds) {
        parts.push(formatDurationSeconds(step.target_duration_seconds));
    }

    return parts.join(' · ') || '—';
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
                        テンプレート一覧
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
                                <th class="px-4 py-3 font-medium">#</th>
                                <th class="px-4 py-3 font-medium">種目</th>
                                <th class="px-4 py-3 font-medium">目的</th>
                                <th class="px-4 py-3 font-medium">目標</th>
                                <th class="px-4 py-3 font-medium">記録形式</th>
                                <th class="px-4 py-3 font-medium">想定時間</th>
                                <th class="px-4 py-3 font-medium">操作</th>
                            </tr>
                        </thead>
                        <ReorderableList
                            v-if="steps.length"
                            :items="steps"
                            :reorder-url="`/routines/${routine.id}/steps/reorder`"
                            :item-label="(step) => step.exercise?.name"
                            variant="table"
                        >
                            <template #row="{ item: step, index }">
                                <td class="px-4 py-3 text-cd-ink-muted">
                                    {{ index + 1 }}
                                </td>
                                <td
                                    class="px-4 py-3 font-serif tracking-[0.06em] text-cd-ink"
                                >
                                    {{ step.exercise?.name ?? '—' }}
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
                                        step.exercise
                                            ? trackingTypeLabels[
                                                  step.exercise.tracking_type
                                              ]
                                            : '—'
                                    }}
                                </td>
                                <td class="px-4 py-3 text-cd-ink-muted">
                                    {{
                                        formatDurationSeconds(
                                            estimateStepDurationSeconds({
                                                target_sets: step.target_sets,
                                                target_duration_seconds:
                                                    step.target_duration_seconds,
                                                rest_seconds: step.rest_seconds,
                                                tracking_type:
                                                    step.exercise
                                                        ?.tracking_type,
                                            }),
                                        )
                                    }}
                                </td>
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
                    </table>
                </div>

                <p
                    v-if="!steps.length"
                    class="px-5 py-12 text-center font-sans text-sm text-cd-ink-muted"
                >
                    ステップがまだありません。
                </p>
            </section>
        </div>
    </div>

    <ExercisePickerDialog
        v-model:open="showAddStepModal"
        v-model:selected-exercise-id="selectedExerciseId"
        v-model:sets="stepSets"
        v-model:reps="stepReps"
        v-model:rest="stepRest"
        :exercises="exercises"
        :saving="saving"
        @submit="addStep"
    />
</template>
