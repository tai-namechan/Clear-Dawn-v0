<script setup lang="ts">
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { apiFetch } from '@/lib/apiFetch';
import { router } from '@inertiajs/vue3';
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
        list.find((card) => card.status === 'pending' && !isCheckinNudge(card.title)) ??
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
    <div v-if="hasContent" class="flex flex-col gap-5">
        <section
            v-if="programContext.some((ctx) => ctx.needs_choice)"
            aria-label="今日のプログラム選択"
        >
            <h2 class="font-sans text-sm font-semibold text-cd-ink">
                今日のプログラム選択
            </h2>
            <ul class="mt-3 flex flex-col gap-3">
                <li
                    v-for="ctx in programContext.filter((c) => c.needs_choice)"
                    :key="ctx.plan_id"
                    class="rounded-xl border border-border/70 px-4 py-3"
                >
                    <p class="font-sans text-sm font-medium text-foreground">
                        W{{ ctx.week_number ?? '-' }} · {{ ctx.day_code }}
                        {{ ctx.day_name }}
                    </p>
                    <p class="mt-1 font-sans text-xs text-muted-foreground">
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
        >
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="min-w-0 flex-1">
                    <p
                        class="font-sans text-xs font-medium uppercase tracking-wide text-muted-foreground"
                    >
                        {{ primaryRecommendation.scope }} ·
                        {{ primaryRecommendation.status }}
                    </p>
                    <h2
                        class="mt-2 font-sans text-xl font-semibold tracking-tight text-cd-ink md:text-2xl"
                    >
                        {{ primaryRecommendation.title }}
                    </h2>
                    <p
                        v-if="primaryRecommendation.rationale"
                        class="mt-3 max-w-2xl font-sans text-sm leading-relaxed text-muted-foreground"
                    >
                        {{ primaryRecommendation.rationale }}
                    </p>
                    <p
                        v-if="primaryRecommendation.goal_impact"
                        class="mt-2 font-sans text-xs text-muted-foreground"
                    >
                        狙い: {{ primaryRecommendation.goal_impact }}
                    </p>
                </div>

                <div
                    v-if="confidenceLabel"
                    class="flex size-20 shrink-0 flex-col items-center justify-center rounded-full border-4 border-primary/30 bg-primary/5 md:size-24"
                >
                    <span
                        v-if="confidenceLabel.percent !== null"
                        class="font-sans text-lg font-bold text-cd-ink"
                    >
                        {{ confidenceLabel.percent }}%
                    </span>
                    <span class="font-sans text-[11px] text-muted-foreground">
                        {{ confidenceLabel.label }}
                    </span>
                </div>
            </div>

            <div
                v-if="primaryRecommendation.status === 'pending'"
                class="mt-5 grid gap-2 sm:grid-cols-2 lg:grid-cols-4"
            >
                <Button
                    v-for="option in primaryRecommendation.options"
                    :key="option.id"
                    type="button"
                    size="lg"
                    :variant="optionVariant(option.action_key)"
                    class="h-auto min-h-12 flex-col gap-0.5 py-3 font-sans"
                    :disabled="decidingId === primaryRecommendation.id"
                    @click="
                        decideRecommendation(
                            primaryRecommendation.id,
                            option.action_key,
                            option.id,
                        )
                    "
                >
                    <span class="text-sm font-semibold">{{
                        option.label
                    }}</span>
                    <span
                        v-if="option.description"
                        class="text-[11px] font-normal opacity-80"
                    >
                        {{ option.description }}
                    </span>
                </Button>
            </div>
            <p
                v-else-if="primaryRecommendation.decision"
                class="mt-4 font-sans text-sm text-muted-foreground"
            >
                決定済み: {{ primaryRecommendation.decision.action_key }}
            </p>
        </section>
    </div>
</template>
