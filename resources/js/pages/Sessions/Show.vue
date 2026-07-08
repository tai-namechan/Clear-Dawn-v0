<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Check,
    Circle,
    CircleCheck,
    CircleStop,
    SkipForward,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { apiFetch } from '@/lib/apiFetch';
import { purposeChipClasses } from '@/lib/stepPurposeColors';
import {
    formatBlockLog,
    formatDurationSeconds,
    formatStepTarget,
    resolveStepPurpose,
    stepPurposeLabels,
    trackingTypeLabels,
} from '@/lib/routineConstants';
import type {
    RoutineSession,
    RoutineSessionStep,
    TrackingType,
    Video,
} from '@/types/routine';

interface Props {
    session: RoutineSession;
}

const props = defineProps<Props>();

const currentIndex = ref(
    Math.max(
        0,
        (props.session.steps ?? []).findIndex(
            (step) => step.status === 'pending',
        ),
    ),
);

const completing = ref(false);
const logging = ref(false);
const playbackUrl = ref<string | null>(null);
const playbackLoading = ref(false);

const blockLoad = ref('');
const blockAmount = ref('');

const steps = computed(() => props.session.steps ?? []);

const currentStep = computed(() => steps.value[currentIndex.value] ?? null);

const trackingType = computed<TrackingType | null>(
    () => currentStep.value?.routine_item?.tracking_type ?? null,
);

const completedCount = computed(
    () => steps.value.filter((step) => step.status === 'completed').length,
);

const progressPercent = computed(() => {
    if (steps.value.length === 0) {
        return 0;
    }

    return Math.round((completedCount.value / steps.value.length) * 100);
});

const completedBlocks = computed(
    () => currentStep.value?.block_logs?.length ?? 0,
);

const targetBlocks = computed(() => currentStep.value?.target_blocks ?? 0);

const planHref = computed(
    () => `/plans/${props.session.routine_plan_id}`,
);

const planTitle = computed(
    () => props.session.routine_plan?.title ?? 'ルーティン実行',
);

function resetBlockForm(): void {
    blockLoad.value = currentStep.value?.target_load ?? '';
    blockAmount.value = currentStep.value?.target_amount ?? '';
}

function goToStep(index: number): void {
    if (index >= 0 && index < steps.value.length) {
        currentIndex.value = index;
        resetBlockForm();
    }
}

async function loadVideo(video: Video | null | undefined): Promise<void> {
    playbackUrl.value = null;

    if (!video || video.status !== 'ready') {
        return;
    }

    playbackLoading.value = true;

    try {
        const result = await apiFetch<{ url: string }>(
            `/videos/${video.id}/stream-url`,
        );
        playbackUrl.value = result.url;
    } catch {
        playbackUrl.value = null;
    } finally {
        playbackLoading.value = false;
    }
}

watch(
    () => currentStep.value?.video,
    (video) => {
        void loadVideo(video);
    },
    { immediate: true },
);

async function logBlock(): Promise<void> {
    if (!currentStep.value) {
        return;
    }

    logging.value = true;

    try {
        const payload: Record<string, unknown> = {};

        if (
            trackingType.value === 'weight_reps' ||
            trackingType.value === 'reps' ||
            trackingType.value === 'count'
        ) {
            if (trackingType.value === 'weight_reps') {
                payload.load_value = blockLoad.value || null;
                payload.load_unit = currentStep.value.load_unit;
            }

            payload.amount_value = blockAmount.value
                ? Number(blockAmount.value)
                : null;
            payload.amount_unit = currentStep.value.amount_unit;
        } else if (
            trackingType.value === 'distance' ||
            trackingType.value === 'duration'
        ) {
            payload.amount_value = blockAmount.value
                ? Number(blockAmount.value)
                : null;
            payload.amount_unit = currentStep.value.amount_unit;
        }

        await apiFetch(
            `/session-steps/${currentStep.value.id}/blocks`,
            {
                method: 'POST',
                body: JSON.stringify(payload),
            },
        );

        router.reload({ only: ['session'] });
    } finally {
        logging.value = false;
    }
}

async function completeStep(): Promise<void> {
    if (!currentStep.value) {
        return;
    }

    await apiFetch(
        `/sessions/${props.session.id}/steps/${currentStep.value.id}`,
        {
            method: 'PATCH',
            body: JSON.stringify({ status: 'completed' }),
        },
    );

    router.reload({
        only: ['session'],
        onSuccess: () => {
            const nextPending = steps.value.findIndex(
                (step) => step.status === 'pending',
            );

            if (nextPending >= 0) {
                currentIndex.value = nextPending;
                resetBlockForm();
            }
        },
    });
}

async function skipStep(): Promise<void> {
    if (!currentStep.value) {
        return;
    }

    await apiFetch(
        `/sessions/${props.session.id}/steps/${currentStep.value.id}`,
        {
            method: 'PATCH',
            body: JSON.stringify({ status: 'skipped' }),
        },
    );

    router.reload({
        only: ['session'],
        onSuccess: () => {
            const nextPending = steps.value.findIndex(
                (step) => step.status === 'pending',
            );

            if (nextPending >= 0) {
                currentIndex.value = nextPending;
                resetBlockForm();
            }
        },
    });
}

async function completeSession(): Promise<void> {
    completing.value = true;

    try {
        await apiFetch(`/sessions/${props.session.id}/complete`, {
            method: 'POST',
        });

        router.visit('/today');
    } finally {
        completing.value = false;
    }
}

async function abortSession(): Promise<void> {
    if (!confirm('実行を中断しますか？')) {
        return;
    }

    await apiFetch(`/sessions/${props.session.id}/abort`, {
        method: 'POST',
    });

    router.visit('/today');
}

function stepPurposeKey(step: RoutineSessionStep) {
    return resolveStepPurpose(
        step.purpose,
        step.routine_item?.category ?? null,
    );
}

function metricValue(
    label: string,
    value: string | number | null | undefined,
): { label: string; value: string } {
    return {
        label,
        value: value !== null && value !== undefined && value !== ''
            ? String(value)
            : '—',
    };
}

const metrics = computed(() => {
    const step = currentStep.value;

    if (!step) {
        return [];
    }

    const type = trackingType.value;

    return [
        metricValue(
            '回数',
            type === 'reps' || type === 'weight_reps' || type === 'count'
                ? step.target_amount
                    ? `${step.target_amount}${step.amount_unit ?? ''}`
                    : null
                : null,
        ),
        metricValue(
            '時間',
            type === 'duration' && step.target_amount
                ? formatDurationSeconds(Number(step.target_amount))
                : null,
        ),
        metricValue(
            'セット',
            step.target_blocks
                ? `${step.target_blocks}${step.routine_item?.category === 'strength' ? 'セット' : 'ブロック'}`
                : null,
        ),
        metricValue(
            'インターバル',
            step.rest_seconds ? `${step.rest_seconds}秒` : null,
        ),
    ];
});

resetBlockForm();
</script>

<template>
    <Head :title="planTitle" />

    <div class="flex min-h-full flex-1 flex-col">
        <div
            class="flex flex-1 flex-col overflow-x-auto rounded-xl p-4 md:px-6 md:pb-28"
        >
            <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6">
                <Link
                    :href="planHref"
                    class="inline-flex items-center gap-2 font-sans text-sm text-cd-ink-muted transition-colors hover:text-cd-ink"
                >
                    <ArrowLeft :size="16" :stroke-width="1.6" />
                    プラン詳細
                </Link>

                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div class="min-w-0">
                        <h1
                            class="font-serif text-2xl tracking-[0.12em] text-cd-ink md:text-3xl"
                        >
                            {{ planTitle }}
                        </h1>
                        <p
                            v-if="currentStep"
                            class="mt-2 font-sans text-sm text-cd-ink-muted"
                        >
                            Step {{ currentIndex + 1 }} / {{ steps.length }}
                        </p>
                    </div>

                    <div class="w-full max-w-xs space-y-2 lg:w-56">
                        <div
                            class="flex items-center justify-between font-sans text-xs text-cd-ink-muted"
                        >
                            <span>完了ステータス</span>
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
                </div>

                <div
                    class="grid flex-1 gap-6 lg:grid-cols-[minmax(0,1fr)_280px]"
                >
                    <section
                        v-if="currentStep"
                        aria-label="現在のステップ"
                        class="cd-shadow-soft flex flex-col rounded-2xl border border-cd-line bg-cd-surface"
                    >
                        <div class="border-b border-cd-line/60 px-5 py-5">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex rounded-full border px-2 py-0.5 font-sans text-xs"
                                    :class="
                                        purposeChipClasses(
                                            stepPurposeKey(currentStep),
                                        )
                                    "
                                >
                                    {{
                                        stepPurposeLabels[
                                            stepPurposeKey(currentStep)
                                        ]
                                    }}
                                </span>
                                <span
                                    v-if="trackingType"
                                    class="font-sans text-xs text-cd-ink-muted"
                                >
                                    {{ trackingTypeLabels[trackingType] }}
                                </span>
                            </div>

                            <h2
                                class="mt-3 font-serif text-xl tracking-[0.1em] text-cd-ink md:text-2xl"
                            >
                                {{ currentStep.item_name }}
                            </h2>

                            <div
                                class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4"
                            >
                                <div
                                    v-for="metric in metrics"
                                    :key="metric.label"
                                    class="rounded-xl border border-cd-line/60 bg-white/40 px-3 py-2"
                                >
                                    <p
                                        class="font-sans text-[11px] tracking-[0.08em] text-cd-ink-muted"
                                    >
                                        {{ metric.label }}
                                    </p>
                                    <p
                                        class="mt-1 font-serif text-sm tracking-[0.06em] text-cd-ink"
                                    >
                                        {{ metric.value }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="px-5 py-5">
                            <div
                                class="overflow-hidden rounded-2xl border border-cd-line/60 bg-black/5"
                            >
                                <div
                                    v-if="playbackLoading"
                                    class="flex aspect-video items-center justify-center font-sans text-sm text-cd-ink-muted"
                                >
                                    動画を読み込み中…
                                </div>
                                <video
                                    v-else-if="playbackUrl"
                                    :src="playbackUrl"
                                    class="aspect-video w-full bg-black object-contain"
                                    controls
                                    playsinline
                                />
                                <div
                                    v-else
                                    class="flex aspect-video items-center justify-center bg-muted/40 px-6 text-center font-sans text-sm text-cd-ink-muted"
                                >
                                    {{
                                        currentStep.video
                                            ? '動画の準備ができていません'
                                            : 'このステップには動画がありません'
                                    }}
                                </div>
                            </div>

                            <div
                                v-if="currentStep.memo || currentStep.video?.description"
                                class="mt-4 rounded-xl border border-cd-line/60 bg-white/40 p-4"
                            >
                                <p
                                    class="mb-2 font-sans text-xs tracking-[0.08em] text-cd-ink-muted"
                                >
                                    ポイント
                                </p>
                                <p
                                    v-if="currentStep.memo"
                                    class="font-sans text-sm leading-relaxed text-cd-ink"
                                >
                                    {{ currentStep.memo }}
                                </p>
                                <p
                                    v-else-if="currentStep.video?.description"
                                    class="font-sans text-sm leading-relaxed text-cd-ink"
                                >
                                    {{ currentStep.video.description }}
                                </p>
                            </div>

                            <div
                                v-if="currentStep.block_logs?.length"
                                class="mt-4 rounded-xl border border-cd-line/60 bg-white/40 p-4"
                            >
                                <p
                                    class="mb-2 font-sans text-xs tracking-[0.08em] text-cd-ink-muted"
                                >
                                    記録済み
                                    <span v-if="targetBlocks">
                                        （{{ completedBlocks }} /
                                        {{ targetBlocks }}）
                                    </span>
                                </p>
                                <ul class="space-y-1 font-sans text-sm text-cd-ink">
                                    <li
                                        v-for="log in currentStep.block_logs"
                                        :key="log.id"
                                    >
                                        ブロック {{ log.block_number }}:
                                        {{ formatBlockLog(log) }}
                                    </li>
                                </ul>
                            </div>

                            <div
                                v-if="trackingType && trackingType !== 'check'"
                                class="mt-4 flex flex-col gap-3"
                            >
                                <div
                                    v-if="
                                        trackingType === 'weight_reps' ||
                                        trackingType === 'reps' ||
                                        trackingType === 'count'
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
                                        v-model="blockLoad"
                                        type="number"
                                        step="0.5"
                                        :placeholder="`負荷 (${currentStep.load_unit ?? 'kg'})`"
                                    />
                                    <Input
                                        v-model="blockAmount"
                                        type="number"
                                        :placeholder="`量 (${currentStep.amount_unit ?? '回'})`"
                                    />
                                </div>

                                <Input
                                    v-if="
                                        trackingType === 'distance' ||
                                        trackingType === 'duration'
                                    "
                                    v-model="blockAmount"
                                    type="number"
                                    step="0.1"
                                    :placeholder="`量 (${currentStep.amount_unit ?? ''})`"
                                />

                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="self-start"
                                    :disabled="logging"
                                    @click="logBlock"
                                >
                                    ブロックを記録
                                </Button>
                            </div>
                        </div>
                    </section>

                    <aside
                        aria-label="ステップ一覧"
                        class="cd-shadow-soft rounded-2xl border border-cd-line bg-cd-surface"
                    >
                        <div
                            class="border-b border-cd-line/60 px-4 py-3 font-serif text-sm tracking-[0.1em] text-cd-ink"
                        >
                            ステップ一覧（{{ steps.length }}）
                        </div>

                        <ul class="max-h-[min(70vh,640px)] overflow-y-auto">
                            <li
                                v-for="(step, index) in steps"
                                :key="step.id"
                            >
                                <button
                                    type="button"
                                    class="flex w-full items-start gap-3 border-b border-cd-line/40 px-4 py-3 text-left transition-colors last:border-b-0"
                                    :class="
                                        index === currentIndex
                                            ? 'bg-primary/8'
                                            : 'hover:bg-white/50'
                                    "
                                    @click="goToStep(index)"
                                >
                                    <CircleCheck
                                        v-if="step.status === 'completed'"
                                        :size="18"
                                        :stroke-width="1.6"
                                        class="mt-0.5 shrink-0 text-cd-moss"
                                    />
                                    <SkipForward
                                        v-else-if="step.status === 'skipped'"
                                        :size="18"
                                        :stroke-width="1.6"
                                        class="mt-0.5 shrink-0 text-cd-ink-muted"
                                    />
                                    <Circle
                                        v-else-if="index === currentIndex"
                                        :size="18"
                                        :stroke-width="1.6"
                                        class="mt-0.5 shrink-0 text-primary"
                                    />
                                    <Circle
                                        v-else
                                        :size="18"
                                        :stroke-width="1.6"
                                        class="mt-0.5 shrink-0 text-cd-ink-muted/50"
                                    />

                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="font-sans text-sm"
                                            :class="
                                                index === currentIndex
                                                    ? 'text-cd-ink'
                                                    : 'text-cd-ink-muted'
                                            "
                                        >
                                            {{ step.item_name }}
                                        </p>
                                        <p
                                            class="mt-0.5 font-sans text-xs text-cd-ink-muted"
                                        >
                                            {{ formatStepTarget(step) }}
                                        </p>
                                    </div>
                                </button>
                            </li>
                        </ul>
                    </aside>
                </div>
            </div>
        </div>

        <div
            class="sticky bottom-0 border-t border-cd-line/80 bg-cd-surface/95 px-4 py-4 backdrop-blur md:px-6"
        >
            <div
                class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-3"
            >
                <div class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="
                            !currentStep || currentStep.status !== 'pending'
                        "
                        @click="skipStep"
                    >
                        <SkipForward :size="16" :stroke-width="1.8" />
                        スキップ
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        @click="abortSession"
                    >
                        <CircleStop :size="16" :stroke-width="1.8" />
                        中断
                    </Button>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="completing || completedCount < steps.length"
                        @click="completeSession"
                    >
                        <Check :size="16" :stroke-width="1.8" />
                        実行完了
                    </Button>
                    <Button
                        type="button"
                        :disabled="
                            !currentStep || currentStep.status !== 'pending'
                        "
                        @click="completeStep"
                    >
                        <Check :size="16" :stroke-width="1.8" />
                        完了して次へ
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
