<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Star } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { apiFetch } from '@/lib/apiFetch';
import type { TodayOps } from '@/types/todayOps';

interface Props {
    date: string;
    ops: TodayOps;
}

const props = defineProps<Props>();

const decidingId = ref<string | null>(null);

const programContext = computed(() => props.ops.program_context ?? []);

/** 主作戦 1 件のみ。チェックイン催促カードの羅列は出さない。 */
const primaryRecommendation = computed(() => {
    const list = props.ops.recommendations ?? [];

    return (
        list.find(
            (card) => card.status === 'pending' && !isCheckinNudge(card.title),
        ) ??
        list.find((card) => !isCheckinNudge(card.title)) ??
        null
    );
});

const confidenceLabel = computed(() => {
    const confidence = primaryRecommendation.value?.confidence;

    if (confidence == null || confidence === '') {
        return null;
    }

    const asNumber = Number(confidence);

    if (!Number.isNaN(asNumber)) {
        if (asNumber >= 80) {
            return { percent: Math.round(asNumber), label: '高い' };
        }

        if (asNumber >= 50) {
            return { percent: Math.round(asNumber), label: 'ふつう' };
        }

        return { percent: Math.round(asNumber), label: '低め' };
    }

    return { percent: null as number | null, label: confidence };
});

const gaugeStyle = computed(() => {
    const percent = confidenceLabel.value?.percent ?? 0;

    return {
        background: `conic-gradient(var(--cd-primary) ${percent}%, #E8E4F0 ${percent}%)`,
    };
});

const hasContent = computed(
    () =>
        programContext.value.some((ctx) => ctx.needs_choice) ||
        primaryRecommendation.value != null,
);

function isCheckinNudge(title: string): boolean {
    return title.includes('チェックイン');
}

async function selectChoice(choiceOptionId: string): Promise<void> {
    await apiFetch('/today/program-choice', {
        method: 'POST',
        body: JSON.stringify({
            date: props.date,
            choice_option_id: choiceOptionId,
        }),
    });
    router.reload({ only: ['ops', 'plans'] });
}

async function decideRecommendation(
    recommendationId: string,
    actionKey: string,
    optionId?: string,
): Promise<void> {
    decidingId.value = recommendationId;

    try {
        await apiFetch(`/recommendations/${recommendationId}/decisions`, {
            method: 'POST',
            body: JSON.stringify({
                action_key: actionKey,
                recommendation_option_id: optionId,
                reason: `routines-today:${actionKey}`,
            }),
        });
        router.reload({ only: ['ops', 'plans'] });
    } finally {
        decidingId.value = null;
    }
}

function optionVariant(actionKey: string): 'default' | 'outline' {
    if (
        actionKey.includes('execute') ||
        actionKey.includes('run') ||
        actionKey === 'accept'
    ) {
        return 'default';
    }

    return 'outline';
}
</script>

<template>
    <div v-if="hasContent" class="flex flex-col gap-4">
        <section
            v-if="programContext.some((ctx) => ctx.needs_choice)"
            aria-label="今日のプログラム選択"
            class="rounded-2xl border border-cd-line bg-white p-4 md:p-5"
        >
            <h2 class="font-sans text-sm font-semibold text-cd-ink">
                今日のプログラム選択
            </h2>
            <ul class="mt-3 flex flex-col gap-3">
                <li
                    v-for="ctx in programContext.filter((c) => c.needs_choice)"
                    :key="ctx.plan_id"
                    class="rounded-xl border border-cd-line px-4 py-3"
                >
                    <p class="font-sans text-sm font-medium text-cd-ink">
                        W{{ ctx.week_number ?? '-' }} · {{ ctx.day_code }}
                        {{ ctx.day_name }}
                    </p>
                    <p class="mt-1 font-sans text-xs text-cd-ink-muted">
                        {{ ctx.title }}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <Button
                            v-for="option in ctx.choice_options"
                            :key="option.id"
                            type="button"
                            size="sm"
                            variant="outline"
                            class="font-sans"
                            @click="selectChoice(option.id)"
                        >
                            {{ option.label }}
                        </Button>
                    </div>
                </li>
            </ul>
        </section>

        <section
            v-if="primaryRecommendation"
            aria-label="今日の作戦"
            class="rounded-2xl border border-[#E4DFF0] bg-[#F7F4FC] p-4 md:p-5"
        >
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="min-w-0 flex-1">
                    <p
                        class="font-sans text-xs font-medium text-primary"
                    >
                        今日の作戦
                    </p>
                    <h2
                        class="mt-1.5 font-sans text-xl font-semibold tracking-tight text-cd-ink md:text-2xl"
                    >
                        {{ primaryRecommendation.title }}
                    </h2>
                    <p
                        v-if="primaryRecommendation.rationale"
                        class="mt-2 max-w-2xl font-sans text-sm leading-relaxed text-cd-ink-muted"
                    >
                        {{ primaryRecommendation.rationale }}
                    </p>

                    <div
                        v-if="primaryRecommendation.goal_impact"
                        class="mt-4 flex gap-2.5 rounded-xl bg-white/90 px-3.5 py-3"
                    >
                        <span
                            class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-primary/15 text-primary"
                        >
                            <Star :size="13" :stroke-width="1.8" />
                        </span>
                        <div class="min-w-0">
                            <p
                                class="font-sans text-sm font-medium text-cd-ink"
                            >
                                狙い:
                                {{ primaryRecommendation.goal_impact }}
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-if="confidenceLabel"
                    class="mx-auto flex size-[4.75rem] shrink-0 items-center justify-center rounded-full p-[5px] lg:mx-0"
                    :style="gaugeStyle"
                    role="img"
                    :aria-label="`確信度 ${confidenceLabel.percent ?? ''} ${confidenceLabel.label}`"
                >
                    <div
                        class="flex size-full flex-col items-center justify-center rounded-full bg-[#F7F4FC]"
                    >
                        <span
                            v-if="confidenceLabel.percent !== null"
                            class="font-sans text-lg font-bold leading-none text-cd-ink"
                        >
                            {{ confidenceLabel.percent }}%
                        </span>
                        <span
                            class="mt-0.5 font-sans text-[10px] text-cd-ink-muted"
                        >
                            {{ confidenceLabel.label }}
                        </span>
                    </div>
                </div>
            </div>

            <div
                v-if="primaryRecommendation.status === 'pending'"
                class="mt-4 flex flex-wrap gap-2"
            >
                <Button
                    v-for="option in primaryRecommendation.options"
                    :key="option.id"
                    type="button"
                    size="sm"
                    :variant="optionVariant(option.action_key)"
                    class="font-sans"
                    :disabled="decidingId === primaryRecommendation.id"
                    @click="
                        decideRecommendation(
                            primaryRecommendation.id,
                            option.action_key,
                            option.id,
                        )
                    "
                >
                    {{ option.label }}
                </Button>
            </div>
            <p
                v-else-if="primaryRecommendation.decision"
                class="mt-4 font-sans text-sm text-cd-ink-muted"
            >
                決定済み: {{ primaryRecommendation.decision.action_key }}
            </p>
        </section>
    </div>
</template>
