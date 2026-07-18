<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, ChevronDown, Plus } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import PageSectionCard from '@/components/PageSectionCard.vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import DailyCheckinPanel from '@/components/routine/DailyCheckinPanel.vue';
import RoutinesHubTabs from '@/components/routine/RoutinesHubTabs.vue';
import TodayPlanCard from '@/components/routine/TodayPlanCard.vue';
import TodayProgressPanel from '@/components/routine/TodayProgressPanel.vue';
import { Button } from '@/components/ui/button';
import { apiFetch } from '@/lib/apiFetch';
import {
    displayDurationMinutes,
    planRunStatus,
} from '@/lib/todayPlanDisplay';
import type { RoutinePlan } from '@/types/routine';
import type { CheckinFormState, TodayOps } from '@/types/todayOps';

interface Props {
    date: string;
    plans: RoutinePlan[];
    ops?: TodayOps;
}

const props = defineProps<Props>();

const showCompleted = ref(false);
const showCheckinEditor = ref(props.ops?.checkin == null);
const checkinForm = reactive<CheckinFormState>({
    sleep_quality: props.ops?.checkin?.sleep_quality ?? 5,
    fatigue: props.ops?.checkin?.fatigue ?? 5,
    muscle_soreness: props.ops?.checkin?.muscle_soreness ?? 5,
    stress: props.ops?.checkin?.stress ?? 5,
    mood: props.ops?.checkin?.mood ?? 5,
    readiness_self: props.ops?.checkin?.readiness_self ?? 5,
});
const decidingId = ref<string | null>(null);
const savingCheckin = ref(false);

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

const programContext = computed(() => props.ops?.program_context ?? []);
const recommendations = computed(() => props.ops?.recommendations ?? []);
const primaryRecommendation = computed(
    () =>
        recommendations.value.find((card) => card.status === 'pending') ??
        recommendations.value[0] ??
        null,
);
const otherRecommendations = computed(() =>
    recommendations.value.filter(
        (card) => card.id !== primaryRecommendation.value?.id,
    ),
);

const nutritionTarget = computed(
    () => props.ops?.nutrition?.profile ?? props.ops?.nutrition?.fallback_goal ?? null,
);
const nutritionIntake = computed(
    () =>
        props.ops?.nutrition?.intake ?? {
            kcal: 0,
            protein_g: 0,
            fat_g: 0,
            carb_g: 0,
        },
);

const remainingKcal = computed(() => {
    if (!nutritionTarget.value) {
        return null;
    }

    return Math.max(
        0,
        Number(nutritionTarget.value.kcal) - Number(nutritionIntake.value.kcal),
    );
});

const kcalProgress = computed(() => {
    if (!nutritionTarget.value) {
        return 0;
    }

    const target = Number(nutritionTarget.value.kcal);

    if (target <= 0) {
        return 0;
    }

    return Math.min(
        100,
        Math.round((Number(nutritionIntake.value.kcal) / target) * 100),
    );
});

const programBadge = computed(() => {
    const ctx = programContext.value[0];

    if (!ctx) {
        return null;
    }

    return `W${ctx.week_number ?? '-'} · ${ctx.day_code ?? ''}`;
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

async function saveCheckin(): Promise<void> {
    savingCheckin.value = true;

    try {
        await apiFetch('/today/checkin', {
            method: 'PUT',
            body: JSON.stringify({
                checked_on: props.date,
                ...checkinForm,
            }),
        });
        showCheckinEditor.value = false;
        router.reload({ only: ['ops', 'plans'] });
    } finally {
        savingCheckin.value = false;
    }
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
                reason: `today-ops:${actionKey}`,
            }),
        });
        router.reload({ only: ['ops', 'plans'] });
    } finally {
        decidingId.value = null;
    }
}

function optionVariant(actionKey: string): 'default' | 'outline' {
    if (actionKey.includes('execute') || actionKey.includes('run') || actionKey === 'accept') {
        return 'default';
    }

    return 'outline';
}
</script>

<template>
    <Head title="今日/作戦" />

    <div class="flex h-full flex-1 flex-col rounded-xl p-4 md:px-6 md:pb-6">
        <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-4">
            <PageSectionCard>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <PageTitleOrnament
                            title="今日の作戦"
                            subtitle="作戦を決めてから、セッションと食事へ進みます。"
                            align="left"
                        />
                        <p
                            v-if="programBadge"
                            class="mt-2 inline-flex rounded-full bg-primary/10 px-3 py-1 font-sans text-xs font-semibold text-primary"
                        >
                            {{ programBadge }}
                        </p>
                    </div>
                </div>
                <div class="mt-5">
                    <RoutinesHubTabs />
                </div>
            </PageSectionCard>

            <section
                v-if="programContext.some((ctx) => ctx.needs_choice)"
                class="rounded-2xl border border-cd-line/80 bg-cd-surface/95 px-5 py-4 shadow-sm"
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

            <!-- 作戦カード（最優先） -->
            <section
                class="rounded-2xl border border-cd-line/80 bg-cd-surface/95 px-5 py-5 shadow-sm md:px-6"
                aria-label="今日の作戦"
            >
                <template v-if="primaryRecommendation">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 flex-1">
                            <p class="font-sans text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                {{ primaryRecommendation.scope }}
                                · {{ primaryRecommendation.status }}
                            </p>
                            <h2 class="mt-2 font-sans text-xl font-semibold tracking-tight text-cd-ink md:text-2xl">
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
                            class="flex size-24 shrink-0 flex-col items-center justify-center rounded-full border-4 border-primary/30 bg-primary/5"
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
                            <span class="text-sm font-semibold">{{ option.label }}</span>
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
                </template>

                <template v-else>
                    <h2 class="font-sans text-lg font-semibold text-cd-ink">
                        今日の作戦カードはまだありません
                    </h2>
                    <p class="mt-2 font-sans text-sm text-muted-foreground">
                        チェックインを記録すると、ルールに応じて作戦カードが生成されます。
                    </p>
                    <Button
                        v-if="!ops?.checkin"
                        type="button"
                        size="sm"
                        class="mt-4 font-sans"
                        @click="showCheckinEditor = true"
                    >
                        チェックインする
                    </Button>
                </template>
            </section>

            <!-- 薄いチェックイン状態 -->
            <div
                v-if="ops?.checkin && !showCheckinEditor"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-cd-line bg-cd-surface/80 px-4 py-3"
            >
                <p class="inline-flex items-center gap-2 font-sans text-sm text-cd-moss">
                    <Check :size="16" :stroke-width="2" />
                    チェックイン済 {{ ops.checkin.checked_on }}
                </p>
                <button
                    type="button"
                    class="inline-flex items-center gap-1 font-sans text-sm font-medium text-primary"
                    @click="showCheckinEditor = true"
                >
                    チェックインを変更
                    <ChevronDown :size="14" :stroke-width="1.6" />
                </button>
            </div>

            <DailyCheckinPanel
                v-if="showCheckinEditor"
                v-model="checkinForm"
                :saving="savingCheckin"
                :has-existing="ops?.checkin != null"
                compact
                @save="saveCheckin"
            />

            <ul
                v-if="otherRecommendations.length > 0"
                class="flex flex-col gap-3"
                aria-label="その他の作戦カード"
            >
                <li
                    v-for="card in otherRecommendations"
                    :key="card.id"
                    class="rounded-xl border px-4 py-3"
                    :class="
                        card.is_interrupt
                            ? 'border-destructive/40 bg-destructive/5'
                            : 'border-border/70 bg-cd-surface/95'
                    "
                >
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-sans text-sm font-medium text-foreground">
                            {{ card.title }}
                        </p>
                        <span class="shrink-0 font-sans text-[10px] uppercase tracking-wide text-muted-foreground">
                            {{ card.scope }} · {{ card.status }}
                        </span>
                    </div>
                    <div
                        v-if="card.status === 'pending'"
                        class="mt-3 flex flex-wrap gap-2"
                    >
                        <Button
                            v-for="option in card.options"
                            :key="option.id"
                            type="button"
                            size="sm"
                            variant="outline"
                            class="font-sans"
                            :disabled="decidingId === card.id"
                            @click="
                                decideRecommendation(
                                    card.id,
                                    option.action_key,
                                    option.id,
                                )
                            "
                        >
                            {{ option.label }}
                        </Button>
                    </div>
                </li>
            </ul>

            <!-- セッション + 食事（二次） -->
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(260px,0.8fr)]">
                <section
                    class="flex min-w-0 flex-col rounded-2xl border border-cd-line/80 bg-cd-surface/95 px-5 py-5 shadow-sm md:px-6"
                    aria-label="今日のセッション"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="font-sans text-lg font-semibold tracking-tight text-cd-ink">
                                今日のセッション
                            </h2>
                            <p class="mt-1 font-sans text-xs text-cd-ink-muted">
                                プログラム生成プランと手動プランをここから開始します。
                            </p>
                        </div>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="shrink-0 font-sans"
                            as-child
                        >
                            <Link href="/routines">
                                <Plus :size="14" :stroke-width="1.8" />
                                追加
                            </Link>
                        </Button>
                    </div>

                    <div class="mt-4">
                        <TodayProgressPanel
                            :date="date"
                            :completed-count="completedCount"
                            :total-count="totalCount"
                            :total-minutes="totalMinutes"
                        />
                    </div>

                    <ul
                        v-if="visiblePlans.length > 0"
                        class="mt-5 flex flex-col gap-3"
                    >
                        <TodayPlanCard
                            v-for="plan in visiblePlans"
                            :key="plan.id"
                            :plan="plan"
                        />
                    </ul>

                    <div
                        v-else-if="plans.length === 0"
                        class="mt-8 flex flex-1 flex-col items-center justify-center py-8 text-center"
                    >
                        <p class="font-sans text-sm text-cd-ink-muted">
                            この日のルーティンはありません。
                        </p>
                        <p class="mt-2 font-sans text-sm text-cd-ink-muted">
                            アクティブなプログラムがあれば自動生成されます。
                        </p>
                    </div>

                    <div
                        v-else
                        class="mt-8 flex flex-1 flex-col items-center justify-center py-6 text-center"
                    >
                        <p class="font-sans text-sm text-cd-ink-muted">
                            未完了のルーティンはありません。
                        </p>
                    </div>

                    <button
                        v-if="completedPlans.length > 0"
                        type="button"
                        class="mt-5 inline-flex items-start gap-1.5 self-start font-sans text-sm text-cd-ink-muted transition-colors hover:text-primary"
                        @click="showCompleted = !showCompleted"
                    >
                        <Plus
                            :size="14"
                            :stroke-width="1.8"
                            class="transition-transform"
                            :class="{ 'rotate-45': showCompleted }"
                        />
                        {{
                            showCompleted
                                ? '完了したルーティンを隠す'
                                : '完了したルーティンを表示'
                        }}
                    </button>
                </section>

                <section
                    class="rounded-2xl border border-cd-line/80 bg-cd-surface/95 px-5 py-5 shadow-sm"
                    aria-label="食事の残り"
                >
                    <h2 class="font-sans text-sm font-semibold text-cd-ink">
                        食事の残り
                    </h2>

                    <template v-if="nutritionTarget && remainingKcal !== null">
                        <p class="mt-3 font-sans text-2xl font-bold text-cd-ink">
                            残り {{ remainingKcal.toLocaleString('ja-JP') }}
                            <span class="text-base font-semibold text-cd-ink-muted">kcal</span>
                        </p>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-cd-line/40">
                            <div
                                class="h-full rounded-full bg-primary transition-[width]"
                                :style="{ width: `${kcalProgress}%` }"
                            />
                        </div>
                        <p class="mt-2 font-sans text-xs text-muted-foreground">
                            {{ Number(nutritionIntake.kcal).toLocaleString('ja-JP') }}
                            /
                            {{ Number(nutritionTarget.kcal).toLocaleString('ja-JP') }} kcal
                        </p>
                        <ul class="mt-4 space-y-1.5 font-sans text-xs text-muted-foreground">
                            <li>
                                たんぱく質
                                {{ Number(nutritionIntake.protein_g).toFixed(0) }}
                                /
                                {{ Number(nutritionTarget.protein_g).toFixed(0) }}g
                            </li>
                            <li>
                                脂質
                                {{ Number(nutritionIntake.fat_g).toFixed(0) }}
                                /
                                {{ Number(nutritionTarget.fat_g).toFixed(0) }}g
                            </li>
                            <li>
                                炭水化物
                                {{ Number(nutritionIntake.carb_g).toFixed(0) }}
                                /
                                {{ Number(nutritionTarget.carb_g).toFixed(0) }}g
                            </li>
                        </ul>
                    </template>
                    <p v-else class="mt-3 font-sans text-sm text-muted-foreground">
                        栄養目標が未設定です。食事記録で目標を設定できます。
                    </p>

                    <div class="mt-4 flex flex-col gap-2">
                        <Link
                            href="/meals"
                            class="font-sans text-sm font-medium text-primary underline-offset-2 hover:underline"
                        >
                            食事を記録
                        </Link>
                        <Link
                            href="/records/condition"
                            class="font-sans text-sm font-medium text-muted-foreground underline-offset-2 hover:underline"
                        >
                            コンディションへ
                        </Link>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
