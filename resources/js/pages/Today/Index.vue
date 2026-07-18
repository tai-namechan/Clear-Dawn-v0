<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import PageSectionCard from '@/components/PageSectionCard.vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
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
import type { TodayOps } from '@/types/todayOps';

interface Props {
    date: string;
    plans: RoutinePlan[];
    ops?: TodayOps;
}

const props = defineProps<Props>();

const showCompleted = ref(false);
const checkinForm = reactive({
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
const nutrition = computed(
    () => props.ops?.nutrition?.profile ?? props.ops?.nutrition?.fallback_goal ?? null,
);

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
</script>

<template>
    <Head title="今日/作戦" />

    <div class="flex h-full flex-1 flex-col rounded-xl p-4 md:px-6 md:pb-6">
        <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-4">
            <PageSectionCard>
                <PageTitleOrnament
                    title="今日/作戦"
                    subtitle="プログラム DAY・チェックイン・作戦カードから今日を決めます。"
                    align="left"
                />
                <div class="mt-5">
                    <RoutinesHubTabs />
                </div>
            </PageSectionCard>

            <section
                v-if="programContext.length > 0"
                class="rounded-2xl border border-cd-line/80 bg-cd-surface/95 px-5 py-4 shadow-sm"
                aria-label="今日のプログラム"
            >
                <h2 class="font-sans text-sm font-semibold text-cd-ink">
                    今日のプログラム
                </h2>
                <ul class="mt-3 flex flex-col gap-3">
                    <li
                        v-for="ctx in programContext"
                        :key="ctx.plan_id"
                        class="rounded-xl border border-border/70 px-4 py-3"
                    >
                        <p class="font-sans text-sm font-medium text-foreground">
                            W{{ ctx.week_number ?? '-' }} · {{ ctx.day_code }}
                            {{ ctx.day_name }}
                        </p>
                        <p class="mt-1 font-sans text-xs text-muted-foreground">
                            {{ ctx.title }}（{{ ctx.status }}）
                        </p>
                        <div
                            v-if="ctx.needs_choice"
                            class="mt-3 flex flex-wrap gap-2"
                        >
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
                class="rounded-2xl border border-cd-line/80 bg-cd-surface/95 px-5 py-4 shadow-sm"
                aria-label="30秒チェックイン"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-sans text-sm font-semibold text-cd-ink">
                            30秒チェックイン
                        </h2>
                        <p class="mt-1 font-sans text-xs text-muted-foreground">
                            睡眠・疲労・張りを 0–10 で記録（未入力なら作戦カードが促します）
                        </p>
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        class="font-sans"
                        :disabled="savingCheckin"
                        @click="saveCheckin"
                    >
                        {{ ops?.checkin ? '更新' : '記録' }}
                    </Button>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-3">
                    <label
                        v-for="field in [
                            ['sleep_quality', '睡眠'],
                            ['fatigue', '疲労'],
                            ['muscle_soreness', '筋肉痛'],
                            ['stress', 'ストレス'],
                            ['mood', '気分'],
                            ['readiness_self', '主観'],
                        ] as const"
                        :key="field[0]"
                        class="flex flex-col gap-1 font-sans text-xs text-muted-foreground"
                    >
                        {{ field[1] }}
                        <input
                            v-model.number="checkinForm[field[0]]"
                            type="number"
                            min="0"
                            max="10"
                            class="rounded-md border border-input bg-background px-2 py-1.5 text-sm text-foreground"
                        />
                    </label>
                </div>
            </section>

            <section
                v-if="recommendations.length > 0"
                class="rounded-2xl border border-cd-line/80 bg-cd-surface/95 px-5 py-4 shadow-sm"
                aria-label="今日の作戦カード"
            >
                <h2 class="font-sans text-sm font-semibold text-cd-ink">
                    今日の作戦カード
                </h2>
                <ul class="mt-3 flex flex-col gap-3">
                    <li
                        v-for="card in recommendations"
                        :key="card.id"
                        class="rounded-xl border px-4 py-3"
                        :class="
                            card.is_interrupt
                                ? 'border-destructive/40 bg-destructive/5'
                                : 'border-border/70'
                        "
                    >
                        <div class="flex items-start justify-between gap-2">
                            <p class="font-sans text-sm font-medium text-foreground">
                                {{ card.title }}
                            </p>
                            <span
                                class="shrink-0 font-sans text-[10px] uppercase tracking-wide text-muted-foreground"
                            >
                                {{ card.scope }} ·
                                {{ card.status }}
                            </span>
                        </div>
                        <p
                            v-if="card.rationale"
                            class="mt-1 font-sans text-xs text-muted-foreground"
                        >
                            {{ card.rationale }}
                        </p>
                        <p
                            v-if="card.goal_impact"
                            class="mt-1 font-sans text-xs text-muted-foreground"
                        >
                            目標への影響: {{ card.goal_impact }}
                        </p>
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
                        <p
                            v-else-if="card.decision"
                            class="mt-2 font-sans text-xs text-muted-foreground"
                        >
                            決定: {{ card.decision.action_key }}
                        </p>
                    </li>
                </ul>
            </section>

            <section
                v-if="nutrition"
                class="rounded-2xl border border-cd-line/80 bg-cd-surface/95 px-5 py-4 shadow-sm"
                aria-label="食事の目安"
            >
                <h2 class="font-sans text-sm font-semibold text-cd-ink">
                    食事の残り目安
                </h2>
                <p class="mt-2 font-sans text-xs text-muted-foreground">
                    {{ nutrition.kcal }} kcal / P {{ nutrition.protein_g }}g / F
                    {{ nutrition.fat_g }}g / C {{ nutrition.carb_g }}g
                </p>
                <Link
                    href="/meals"
                    class="mt-2 inline-block font-sans text-xs font-medium text-primary underline-offset-2 hover:underline"
                >
                    食事記録へ
                </Link>
            </section>

            <div
                class="flex min-h-0 flex-1 flex-col gap-4 lg:flex-row lg:items-stretch"
            >
                <TodayProgressPanel
                    :date="date"
                    :completed-count="completedCount"
                    :total-count="totalCount"
                    :total-minutes="totalMinutes"
                />

                <section
                    class="flex min-w-0 flex-1 flex-col rounded-2xl border border-cd-line/80 bg-cd-surface/95 px-5 py-5 shadow-sm md:px-6"
                    aria-label="今日のトレーニング・練習"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h1
                                class="font-sans text-lg font-semibold tracking-tight text-cd-ink"
                            >
                                今日のセッション
                            </h1>
                            <p
                                class="mt-1 font-sans text-xs text-cd-ink-muted"
                            >
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
                                ルーティンを追加
                            </Link>
                        </Button>
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
                        class="mt-8 flex flex-1 flex-col items-center justify-center py-10 text-center"
                    >
                        <p class="font-sans text-sm text-cd-ink-muted">
                            この日のルーティンはありません。
                        </p>
                        <p class="mt-2 font-sans text-sm text-cd-ink-muted">
                            アクティブなプログラムがあれば自動生成されます。または
                            <Link
                                href="/routines"
                                class="font-medium text-primary underline-offset-2 hover:underline"
                            >
                                ルーティン
                            </Link>
                            から載せてください。
                        </p>
                    </div>

                    <div
                        v-else
                        class="mt-8 flex flex-1 flex-col items-center justify-center py-8 text-center"
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
            </div>
        </div>
    </div>
</template>
