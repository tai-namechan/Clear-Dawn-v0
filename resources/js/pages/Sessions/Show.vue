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
import PageSectionCard from '@/components/PageSectionCard.vue';
import SessionBlockLogger from '@/components/routine/SessionBlockLogger.vue';
import { Button } from '@/components/ui/button';
import { apiFetch } from '@/lib/apiFetch';
import {
    formatDurationSeconds,
    formatStepTarget,
    resolveStepPurpose,
    stepPurposeLabels,
    trackingTypeLabels,
} from '@/lib/routineConstants';
import { purposeChipClasses } from '@/lib/stepPurposeColors';
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

const steps = computed(() => props.session.steps ?? []);

const currentStep = computed(() => steps.value[currentIndex.value] ?? null);

const trackingType = computed<TrackingType | null>(
    () => currentStep.value?.routine_item?.tracking_type ?? null,
);

const completedCount = computed(
    () => steps.value.filter((step) => step.status === 'completed').length,
);

const resolvedCount = computed(
    () =>
        steps.value.filter(
            (step) =>
                step.status === 'completed' || step.status === 'skipped',
        ).length,
);

const allStepsResolved = computed(
    () =>
        steps.value.length > 0 &&
        resolvedCount.value >= steps.value.length,
);

const progressPercent = computed(() => {
    if (steps.value.length === 0) {
        return 0;
    }

    return Math.round((resolvedCount.value / steps.value.length) * 100);
});

const targetBlocks = computed(() => currentStep.value?.target_blocks ?? 0);

const planHref = computed(
    () => `/plans/${props.session.routine_plan_id}`,
);

const planTitle = computed(
    () => props.session.routine_plan?.title ?? 'ルーティン実行',
);

const isSessionFinished = computed(
    () =>
        props.session.status === 'completed' ||
        props.session.status === 'aborted',
);

function goToStep(index: number): void {
    if (index >= 0 && index < steps.value.length) {
        currentIndex.value = index;
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

async function logBlock(payload: {
    load_value?: string | null;
    amount_value?: number | null;
    amount_unit?: string | null;
    load_unit?: string | null;
}): Promise<void> {
    if (!currentStep.value) {
        return;
    }

    logging.value = true;

    try {
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

function advanceToNextPending(): void {
    const nextPending = steps.value.findIndex(
        (step) => step.status === 'pending',
    );

    if (nextPending >= 0) {
        currentIndex.value = nextPending;
    }
}

async function completeStep(): Promise<void> {
    if (!currentStep.value || currentStep.value.status !== 'pending') {
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
            advanceToNextPending();
        },
    });
}

async function skipStep(): Promise<void> {
    if (!currentStep.value || currentStep.value.status !== 'pending') {
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
            advanceToNextPending();
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
    const items = [
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

    return items.filter((item) => item.value !== '—');
});

</script>

<template>
    <Head :title="planTitle" />

    <div class="flex min-h-0 flex-1 flex-col rounded-xl p-4 md:px-6 md:pb-6">
        <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-4">
            <PageSectionCard>
                <div class="flex flex-col gap-4">
                    <Link
                        :href="planHref"
                        class="inline-flex items-center gap-2 font-sans text-sm font-medium text-cd-ink-muted transition-colors hover:text-primary"
                    >
                        <ArrowLeft :size="16" :stroke-width="1.6" />
                        プラン詳細
                    </Link>

                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div class="min-w-0">
                            <h1
                                class="font-sans text-2xl font-semibold tracking-tight text-cd-ink md:text-3xl"
                            >
                                {{ planTitle }}
                            </h1>
                            <p
                                v-if="allStepsResolved"
                                class="mt-2 font-sans text-sm text-cd-ink-muted"
                            >
                                全 {{ steps.length }} ステップ処理済み
                            </p>
                            <p
                                v-else-if="currentStep"
                                class="mt-2 font-sans text-sm text-cd-ink-muted"
                            >
                                Step {{ currentIndex + 1 }} /
                                {{ steps.length }}
                            </p>
                        </div>

                        <div class="w-full max-w-xs space-y-2 lg:w-56">
                            <div
                                class="flex items-center justify-between font-sans text-xs text-cd-ink-muted"
                            >
                                <span>進捗</span>
                                <span>
                                    {{ resolvedCount }} /
                                    {{ steps.length }}（{{
                                        progressPercent
                                    }}%）
                                </span>
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
                                    :style="{
                                        width: `${progressPercent}%`,
                                    }"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </PageSectionCard>

            <div
                class="grid flex-1 gap-6 lg:grid-cols-[minmax(0,1fr)_280px]"
            >
                    <section
                        v-if="allStepsResolved && !isSessionFinished"
                        aria-label="実行のまとめ"
                        class="cd-panel flex flex-col px-5 py-8"
                    >
                        <div class="mx-auto max-w-md text-center">
                            <CircleCheck
                                :size="40"
                                :stroke-width="1.5"
                                class="mx-auto text-cd-moss"
                            />
                            <h2
                                class="mt-4 font-sans text-xl font-semibold text-cd-ink"
                            >
                                すべてのステップが終わりました
                            </h2>
                            <p
                                class="mt-2 font-sans text-sm text-cd-ink-muted"
                            >
                                完了 {{ completedCount }} /
                                {{ steps.length }}
                                <span
                                    v-if="
                                        resolvedCount - completedCount > 0
                                    "
                                    class="before:mx-1.5 before:content-['·']"
                                >
                                    スキップ
                                    {{ resolvedCount - completedCount }}
                                </span>
                            </p>
                            <p
                                class="mt-3 font-sans text-sm text-cd-ink-muted"
                            >
                                「実行を完了する」を押すと今日/作戦へ戻ります。履歴にも残ります。
                            </p>
                            <Button
                                type="button"
                                class="mt-6"
                                :disabled="completing"
                                @click="completeSession"
                            >
                                <Check :size="16" :stroke-width="1.8" />
                                {{
                                    completing
                                        ? '完了処理中…'
                                        : '実行を完了する'
                                }}
                            </Button>
                        </div>
                    </section>

                    <section
                        v-else-if="isSessionFinished"
                        aria-label="実行済み"
                        class="cd-panel flex flex-col px-5 py-8"
                    >
                        <div class="mx-auto max-w-md text-center">
                            <h2
                                class="font-sans text-xl font-semibold text-cd-ink"
                            >
                                {{
                                    session.status === 'completed'
                                        ? 'この実行は完了済みです'
                                        : 'この実行は中断されました'
                                }}
                            </h2>
                            <p
                                class="mt-2 font-sans text-sm text-cd-ink-muted"
                            >
                                今日/作戦画面から、別の予定を開始できます。
                            </p>
                            <Button type="button" class="mt-6" as-child>
                                <Link href="/today">今日/作戦へ戻る</Link>
                            </Button>
                        </div>
                    </section>

                    <section
                        v-else-if="currentStep"
                        aria-label="現在のステップ"
                        class="cd-panel flex flex-col"
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
                                class="mt-3 font-sans text-xl font-semibold tracking-tight text-cd-ink md:text-2xl"
                            >
                                {{ currentStep.item_name }}
                            </h2>

                            <div
                                v-if="metrics.length"
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
                                        class="mt-1 font-sans text-sm font-medium text-cd-ink"
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

                            <div class="mt-4">
                                <SessionBlockLogger
                                    v-if="trackingType && currentStep"
                                    :tracking-type="trackingType"
                                    :target-blocks="targetBlocks"
                                    :completed-logs="
                                        currentStep.block_logs ?? []
                                    "
                                    :load-unit="currentStep.load_unit"
                                    :amount-unit="currentStep.amount_unit"
                                    :default-load="currentStep.target_load"
                                    :default-amount="
                                        currentStep.target_amount
                                    "
                                    :logging="logging"
                                    @log="logBlock"
                                />
                            </div>
                        </div>
                    </section>

                    <section
                        v-else
                        aria-label="ステップなし"
                        class="cd-panel flex flex-col px-5 py-8 text-center"
                    >
                        <p class="font-sans text-sm text-cd-ink-muted">
                            表示できるステップがありません。
                        </p>
                    </section>

                    <aside
                        aria-label="ステップ一覧と操作"
                        class="cd-panel flex flex-col lg:sticky lg:top-4 lg:self-start"
                    >
                        <div
                            class="border-b border-cd-line px-4 py-3 font-sans text-sm font-semibold text-cd-ink"
                        >
                            ステップ一覧（{{ steps.length }}）
                        </div>

                        <ul class="max-h-[min(50vh,420px)] overflow-y-auto">
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

                        <div
                            v-if="!isSessionFinished"
                            class="flex flex-col gap-2 border-t border-cd-line p-3"
                        >
                            <Button
                                v-if="allStepsResolved"
                                type="button"
                                class="w-full"
                                :disabled="completing"
                                @click="completeSession"
                            >
                                <Check :size="16" :stroke-width="1.8" />
                                {{
                                    completing
                                        ? '完了処理中…'
                                        : '実行を完了する'
                                }}
                            </Button>
                            <Button
                                v-else
                                type="button"
                                class="w-full"
                                :disabled="
                                    !currentStep ||
                                    currentStep.status !== 'pending'
                                "
                                @click="completeStep"
                            >
                                <Check :size="16" :stroke-width="1.8" />
                                完了して次へ
                            </Button>

                            <div class="grid grid-cols-2 gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="w-full"
                                    :disabled="
                                        !currentStep ||
                                        currentStep.status !== 'pending' ||
                                        allStepsResolved
                                    "
                                    @click="skipStep"
                                >
                                    <SkipForward
                                        :size="16"
                                        :stroke-width="1.8"
                                    />
                                    スキップ
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    class="w-full"
                                    :disabled="completing"
                                    @click="abortSession"
                                >
                                    <CircleStop
                                        :size="16"
                                        :stroke-width="1.8"
                                    />
                                    中断
                                </Button>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
</template>
