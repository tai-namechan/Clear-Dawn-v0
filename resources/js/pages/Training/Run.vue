<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Check,
    ChevronLeft,
    ChevronRight,
    CircleStop,
    SkipForward,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { apiFetch } from '@/lib/apiFetch';
import { purposeChipClasses } from '@/lib/stepPurposeColors';
import {
    resolveStepPurpose,
    stepPurposeLabels,
    trackingTypeLabels,
} from '@/lib/trainingConstants';
import type {
    TrainingRun,
    TrainingRunStep,
    TrackingType,
} from '@/types/training';

interface Props {
    run: TrainingRun;
}

const props = defineProps<Props>();

const currentIndex = ref(
    Math.max(
        0,
        (props.run.steps ?? []).findIndex(
            (step) => step.status === 'pending',
        ),
    ),
);

const completing = ref(false);
const logging = ref(false);

const setWeight = ref('');
const setReps = ref('');
const setDistance = ref('');
const setDuration = ref('');

const steps = computed(() => props.run.steps ?? []);

const currentStep = computed(
    () => steps.value[currentIndex.value] ?? null,
);

const trackingType = computed<TrackingType | null>(
    () => currentStep.value?.exercise?.tracking_type ?? null,
);

const completedCount = computed(
    () =>
        steps.value.filter((step) => step.status === 'completed').length,
);

const progressPercent = computed(() => {
    if (steps.value.length === 0) {
        return 0;
    }

    return Math.round((completedCount.value / steps.value.length) * 100);
});

function resetSetForm(): void {
    setWeight.value = currentStep.value?.target_weight_kg ?? '';
    setReps.value =
        currentStep.value?.target_reps?.toString() ?? '';
    setDistance.value = currentStep.value?.target_distance_m ?? '';
    setDuration.value =
        currentStep.value?.target_duration_seconds?.toString() ?? '';
}

function goToStep(index: number): void {
    if (index >= 0 && index < steps.value.length) {
        currentIndex.value = index;
        resetSetForm();
    }
}

async function logSet(): Promise<void> {
    if (!currentStep.value) {
        return;
    }

    logging.value = true;

    try {
        const payload: Record<string, unknown> = {};

        if (trackingType.value === 'weight_reps') {
            payload.weight_kg = setWeight.value || null;
            payload.reps = setReps.value ? Number(setReps.value) : null;
        } else if (trackingType.value === 'reps') {
            payload.reps = setReps.value ? Number(setReps.value) : null;
        } else if (trackingType.value === 'distance') {
            payload.distance_m = setDistance.value || null;
        } else if (trackingType.value === 'duration') {
            payload.duration_seconds = setDuration.value
                ? Number(setDuration.value)
                : null;
        }

        await apiFetch(
            `/training/run-steps/${currentStep.value.id}/sets`,
            {
                method: 'POST',
                body: JSON.stringify(payload),
            },
        );

        router.reload({ only: ['run'] });
    } finally {
        logging.value = false;
    }
}

async function completeStep(): Promise<void> {
    if (!currentStep.value) {
        return;
    }

    await apiFetch(
        `/training/runs/${props.run.id}/steps/${currentStep.value.id}`,
        {
            method: 'PATCH',
            body: JSON.stringify({ status: 'completed' }),
        },
    );

    router.reload({
        only: ['run'],
        onSuccess: () => {
            const nextPending = steps.value.findIndex(
                (step) => step.status === 'pending',
            );

            if (nextPending >= 0) {
                currentIndex.value = nextPending;
                resetSetForm();
            }
        },
    });
}

async function skipStep(): Promise<void> {
    if (!currentStep.value) {
        return;
    }

    await apiFetch(
        `/training/runs/${props.run.id}/steps/${currentStep.value.id}`,
        {
            method: 'PATCH',
            body: JSON.stringify({ status: 'skipped' }),
        },
    );

    router.reload({ only: ['run'] });
}

async function completeRun(): Promise<void> {
    completing.value = true;

    try {
        await apiFetch(`/training/runs/${props.run.id}/complete`, {
            method: 'POST',
        });

        router.visit('/training');
    } finally {
        completing.value = false;
    }
}

async function abortRun(): Promise<void> {
    if (!confirm('トレーニングを中断しますか？')) {
        return;
    }

    await apiFetch(`/training/runs/${props.run.id}/abort`, {
        method: 'POST',
    });

    router.visit('/training');
}

function stepPurposeKey(step: TrainingRunStep) {
    return resolveStepPurpose(
        step.purpose,
        step.exercise?.category ?? null,
    );
}

resetSetForm();
</script>

<template>
    <Head :title="run.training_plan?.title ?? 'トレーニング実行'" />

    <div
        class="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4 md:px-6 md:pb-6"
    >
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6">
            <PageTitleOrnament
                :title="run.training_plan?.title ?? 'トレーニング'"
                subtitle="ステップごとに記録しながら進めましょう。"
                align="left"
            />

            <div class="space-y-2">
                <div class="flex items-center justify-between font-sans text-xs text-cd-ink-muted">
                    <span>{{ completedCount }} / {{ steps.length }} 完了</span>
                    <span>{{ progressPercent }}%</span>
                </div>
                <div
                    class="h-2 overflow-hidden rounded-full bg-muted"
                    role="progressbar"
                    :aria-valuenow="progressPercent"
                    aria-valuemin="0"
                    aria-valuemax="100"
                >
                    <div
                        class="h-full bg-primary transition-all"
                        :style="{ width: `${progressPercent}%` }"
                    />
                </div>
            </div>

            <section
                v-if="currentStep"
                aria-label="現在のステップ"
                class="cd-shadow-soft rounded-2xl border border-cd-line bg-cd-surface px-5 py-6"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="font-sans text-xs tracking-[0.08em] text-cd-ink-muted"
                    >
                        STEP {{ currentIndex + 1 }}
                    </span>
                    <span
                        class="inline-flex rounded-full border px-2 py-0.5 font-sans text-xs"
                        :class="purposeChipClasses(stepPurposeKey(currentStep))"
                    >
                        {{
                            stepPurposeLabels[stepPurposeKey(currentStep)]
                        }}
                    </span>
                </div>

                <h2
                    class="mt-3 font-serif text-2xl tracking-[0.1em] text-cd-ink"
                >
                    {{ currentStep.exercise_name }}
                </h2>

                <p class="mt-2 font-sans text-sm text-cd-ink-muted">
                    <template v-if="currentStep.target_sets">
                        {{ currentStep.target_sets }}セット
                    </template>
                    <template v-if="currentStep.target_reps">
                        · {{ currentStep.target_reps }}回
                    </template>
                    <template v-if="currentStep.target_weight_kg">
                        · {{ currentStep.target_weight_kg }}kg
                    </template>
                    <span
                        v-if="trackingType"
                        class="before:mx-1.5 before:content-['·']"
                    >
                        {{ trackingTypeLabels[trackingType] }}
                    </span>
                </p>

                <div
                    v-if="currentStep.set_logs?.length"
                    class="mt-4 rounded-xl border border-cd-line/60 bg-white/40 p-3"
                >
                    <p class="mb-2 font-sans text-xs text-cd-ink-muted">
                        記録済みセット
                    </p>
                    <ul class="space-y-1 font-sans text-sm text-cd-ink">
                        <li
                            v-for="log in currentStep.set_logs"
                            :key="log.id"
                        >
                            セット {{ log.set_number }}:
                            <template v-if="log.weight_kg">
                                {{ log.weight_kg }}kg
                            </template>
                            <template v-if="log.reps">
                                × {{ log.reps }}回
                            </template>
                            <template v-if="log.distance_m">
                                {{ log.distance_m }}m
                            </template>
                            <template v-if="log.duration_seconds">
                                {{ log.duration_seconds }}秒
                            </template>
                        </li>
                    </ul>
                </div>

                <div
                    v-if="trackingType && trackingType !== 'check'"
                    class="mt-5 flex flex-col gap-3"
                >
                    <div
                        v-if="
                            trackingType === 'weight_reps' ||
                            trackingType === 'reps'
                        "
                        class="grid gap-2"
                        :class="
                            trackingType === 'weight_reps'
                                ? 'grid-cols-2'
                                : 'grid-cols-1'
                        "
                    >
                        <Input
                            v-if="trackingType === 'weight_reps'"
                            v-model="setWeight"
                            type="number"
                            step="0.5"
                            placeholder="重量 (kg)"
                        />
                        <Input
                            v-model="setReps"
                            type="number"
                            placeholder="回数"
                        />
                    </div>

                    <Input
                        v-if="trackingType === 'distance'"
                        v-model="setDistance"
                        type="number"
                        step="0.1"
                        placeholder="距離 (m)"
                    />

                    <Input
                        v-if="trackingType === 'duration'"
                        v-model="setDuration"
                        type="number"
                        placeholder="時間 (秒)"
                    />

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="logging"
                        @click="logSet"
                    >
                        セットを記録
                    </Button>
                </div>

                <div class="mt-6 flex flex-wrap gap-2">
                    <Button
                        type="button"
                        :disabled="currentStep.status !== 'pending'"
                        @click="completeStep"
                    >
                        <Check :size="16" :stroke-width="1.8" />
                        ステップ完了
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="currentStep.status !== 'pending'"
                        @click="skipStep"
                    >
                        <SkipForward :size="16" :stroke-width="1.8" />
                        スキップ
                    </Button>
                </div>
            </section>

            <div class="flex items-center justify-between gap-3">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    :disabled="currentIndex === 0"
                    @click="goToStep(currentIndex - 1)"
                >
                    <ChevronLeft :size="16" :stroke-width="1.6" />
                    前へ
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    :disabled="currentIndex >= steps.length - 1"
                    @click="goToStep(currentIndex + 1)"
                >
                    次へ
                    <ChevronRight :size="16" :stroke-width="1.6" />
                </Button>
            </div>

            <div class="flex flex-wrap gap-2 border-t border-cd-line/60 pt-4">
                <Button
                    type="button"
                    :disabled="completing || completedCount < steps.length"
                    @click="completeRun"
                >
                    <Check :size="16" :stroke-width="1.8" />
                    トレーニング完了
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    @click="abortRun"
                >
                    <CircleStop :size="16" :stroke-width="1.8" />
                    中断
                </Button>
            </div>
        </div>
    </div>
</template>
