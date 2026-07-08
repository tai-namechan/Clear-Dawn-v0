<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, CirclePlay, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { apiFetch } from '@/lib/apiFetch';
import { fetchExercisesFromPage } from '@/lib/fetchExercises';
import { purposeChipClasses } from '@/lib/stepPurposeColors';
import {
    formatDurationSeconds,
    resolveStepPurpose,
    stepPurposeLabels,
    trainingPlanStatusLabels,
    trackingTypeLabels,
} from '@/lib/trainingConstants';
import type {
    Exercise,
    TrainingPlan,
    TrainingPlanStep,
} from '@/types/training';

interface Props {
    plan: TrainingPlan;
}

const props = defineProps<Props>();

const title = ref(props.plan.title);
const note = ref(props.plan.note ?? '');
const saving = ref(false);
const starting = ref(false);
const showAddStepModal = ref(false);

const selectedExerciseId = ref('');
const stepSets = ref('3');
const stepReps = ref('');
const stepRest = ref('60');

const exercises = ref<Exercise[]>([]);

const canStart = computed(
    () =>
        props.plan.status === 'ready' &&
        (props.plan.steps?.length ?? 0) > 0 &&
        !activeRun.value,
);

const activeRun = computed(
    () => props.plan.runs?.find((run) => run.status === 'in_progress') ?? null,
);

async function savePlan(): Promise<void> {
    saving.value = true;

    try {
        await apiFetch(`/training/plans/${props.plan.id}`, {
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

async function markReady(): Promise<void> {
    saving.value = true;

    try {
        await apiFetch(`/training/plans/${props.plan.id}`, {
            method: 'PATCH',
            body: JSON.stringify({ status: 'ready' }),
        });

        router.reload({ only: ['plan'] });
    } finally {
        saving.value = false;
    }
}

async function startRun(): Promise<void> {
    starting.value = true;

    try {
        const result = await apiFetch<{ run: { id: string } }>(
            `/training/plans/${props.plan.id}/runs`,
            { method: 'POST' },
        );

        router.visit(`/training/runs/${result.run.id}`);
    } finally {
        starting.value = false;
    }
}

async function loadExercises(): Promise<void> {
    exercises.value = await fetchExercisesFromPage();
    selectedExerciseId.value = exercises.value[0]?.id ?? '';
}

async function openAddStep(): Promise<void> {
    await loadExercises();
    showAddStepModal.value = true;
}

async function addStep(): Promise<void> {
    if (!selectedExerciseId.value) {
        return;
    }

    saving.value = true;

    try {
        await apiFetch(`/training/plans/${props.plan.id}/steps`, {
            method: 'POST',
            body: JSON.stringify({
                exercise_id: selectedExerciseId.value,
                target_sets: stepSets.value ? Number(stepSets.value) : null,
                target_reps: stepReps.value ? Number(stepReps.value) : null,
                rest_seconds: stepRest.value ? Number(stepRest.value) : null,
            }),
        });

        showAddStepModal.value = false;
        router.reload({ only: ['plan'] });
    } finally {
        saving.value = false;
    }
}

async function deleteStep(step: TrainingPlanStep): Promise<void> {
    if (!confirm('このステップを削除しますか？')) {
        return;
    }

    await apiFetch(`/training/plans/${props.plan.id}/steps/${step.id}`, {
        method: 'DELETE',
    });
    router.reload({ only: ['plan'] });
}

async function deletePlan(): Promise<void> {
    if (!confirm('このメニューを削除しますか？')) {
        return;
    }

    await apiFetch(`/training/plans/${props.plan.id}`, {
        method: 'DELETE',
    });
    router.visit('/training');
}

function stepPurposeKey(step: TrainingPlanStep) {
    return resolveStepPurpose(step.purpose, step.exercise?.category ?? null);
}

function formatStepTarget(step: TrainingPlanStep): string {
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
    <Head :title="plan.title" />

    <div
        class="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4 md:px-6 md:pb-6"
    >
        <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6">
            <Link
                href="/training"
                class="inline-flex items-center gap-2 font-sans text-sm text-cd-ink-muted transition-colors hover:text-cd-ink"
            >
                <ArrowLeft :size="16" :stroke-width="1.6" />
                今日のメニュー
            </Link>

            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <PageTitleOrnament
                    :title="plan.title"
                    :subtitle="`${plan.scheduled_on} · ${trainingPlanStatusLabels[plan.status]}`"
                    align="left"
                />

                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="activeRun"
                        type="button"
                        @click="router.visit(`/training/runs/${activeRun.id}`)"
                    >
                        <CirclePlay :size="16" :stroke-width="1.6" />
                        実行を続ける
                    </Button>
                    <Button
                        v-else-if="canStart"
                        type="button"
                        :disabled="starting"
                        @click="startRun"
                    >
                        <CirclePlay :size="16" :stroke-width="1.6" />
                        開始
                    </Button>
                    <Button
                        v-if="plan.status === 'draft'"
                        type="button"
                        variant="outline"
                        :disabled="saving || !plan.steps?.length"
                        @click="markReady"
                    >
                        準備完了にする
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="メニューを削除"
                        @click="deletePlan"
                    >
                        <Trash2 :size="16" :stroke-width="1.6" />
                    </Button>
                </div>
            </div>

            <section
                class="cd-shadow-soft rounded-2xl border border-cd-line bg-cd-surface px-5 py-5"
            >
                <div class="flex flex-col gap-3">
                    <Input
                        v-model="title"
                        placeholder="メニュー名"
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
            </section>

            <div class="flex items-center justify-between gap-3">
                <h2 class="font-serif text-base tracking-[0.12em] text-cd-ink">
                    ステップ
                </h2>
                <Button type="button" size="sm" @click="openAddStep">
                    <Plus :size="14" :stroke-width="1.8" />
                    追加
                </Button>
            </div>

            <section
                aria-label="ステップ一覧"
                class="cd-shadow-soft overflow-hidden rounded-2xl border border-cd-line bg-cd-surface"
            >
                <ul v-if="plan.steps?.length" class="flex flex-col">
                    <li
                        v-for="(step, index) in plan.steps"
                        :key="step.id"
                        class="flex items-center justify-between gap-3 border-b border-cd-line/60 px-5 py-4 last:border-b-0"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="font-sans text-xs text-cd-ink-muted"
                                >
                                    {{ index + 1 }}
                                </span>
                                <span
                                    class="font-serif text-base tracking-[0.06em] text-cd-ink"
                                >
                                    {{ step.exercise?.name ?? '—' }}
                                </span>
                                <span
                                    class="inline-flex rounded-full border px-2 py-0.5 font-sans text-xs"
                                    :class="
                                        purposeChipClasses(stepPurposeKey(step))
                                    "
                                >
                                    {{
                                        stepPurposeLabels[stepPurposeKey(step)]
                                    }}
                                </span>
                            </div>
                            <p class="mt-1 font-sans text-xs text-cd-ink-muted">
                                {{ formatStepTarget(step) }}
                                <span
                                    class="before:mx-1.5 before:content-['·']"
                                >
                                    {{
                                        step.exercise
                                            ? trackingTypeLabels[
                                                  step.exercise.tracking_type
                                              ]
                                            : ''
                                    }}
                                </span>
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            aria-label="ステップを削除"
                            @click="deleteStep(step)"
                        >
                            <Trash2 :size="14" :stroke-width="1.6" />
                        </Button>
                    </li>
                </ul>

                <p
                    v-else
                    class="px-5 py-12 text-center font-sans text-sm text-cd-ink-muted"
                >
                    ステップを追加してください。
                </p>
            </section>
        </div>
    </div>

    <Dialog
        :open="showAddStepModal"
        @update:open="(v) => (showAddStepModal = v)"
    >
        <DialogContent class="bg-cd-surface sm:max-w-md">
            <DialogHeader>
                <DialogTitle
                    class="font-serif text-lg tracking-[0.12em] text-cd-ink"
                >
                    ステップを追加
                </DialogTitle>
            </DialogHeader>

            <div class="flex flex-col gap-3">
                <Select v-model="selectedExerciseId" :disabled="saving">
                    <SelectTrigger>
                        <SelectValue placeholder="種目を選択" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="exercise in exercises"
                            :key="exercise.id"
                            :value="exercise.id"
                        >
                            {{ exercise.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <div class="grid grid-cols-3 gap-2">
                    <Input
                        v-model="stepSets"
                        type="number"
                        min="1"
                        placeholder="セット"
                    />
                    <Input
                        v-model="stepReps"
                        type="number"
                        min="1"
                        placeholder="回数"
                    />
                    <Input
                        v-model="stepRest"
                        type="number"
                        min="0"
                        placeholder="休憩(秒)"
                    />
                </div>
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="ghost"
                    @click="showAddStepModal = false"
                >
                    キャンセル
                </Button>
                <Button
                    type="button"
                    :disabled="!selectedExerciseId"
                    @click="addStep"
                >
                    追加
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
