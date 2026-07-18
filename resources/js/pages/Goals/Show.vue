<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import PageSectionCard from '@/components/PageSectionCard.vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    store as storeMetric,
    destroy as destroyMetric,
} from '@/routes/goal-metrics';
import { index, destroy as destroyGoal, update } from '@/routes/goals';
import { show as showProgram } from '@/routes/programs';
import type { GoalSummary, MetricOption } from '@/types/program';

interface Props {
    goal: GoalSummary;
    availableMetrics: MetricOption[];
}

const props = defineProps<Props>();

const statusLabels: Record<string, string> = {
    draft: '下書き',
    active: '進行中',
    achieved: '達成',
    abandoned: '中止',
};

const editReason = ref('');
const editingStatus = ref(props.goal.status);
const newMetricId = ref('');
const newMetricTarget = ref('');

function saveStatus(): void {
    if (editReason.value.trim() === '') {
        return;
    }

    router.patch(
        update.url(props.goal.id),
        { status: editingStatus.value, reason: editReason.value },
        { preserveScroll: true, onSuccess: () => (editReason.value = '') },
    );
}

function addMetric(): void {
    if (newMetricId.value === '') {
        return;
    }

    router.post(
        storeMetric.url(props.goal.id),
        {
            metric_id: newMetricId.value,
            target_value:
                newMetricTarget.value === '' ? null : newMetricTarget.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                newMetricId.value = '';
                newMetricTarget.value = '';
            },
        },
    );
}

function removeMetric(goalMetricId: string): void {
    router.delete(destroyMetric.url(goalMetricId), { preserveScroll: true });
}

function removeGoal(): void {
    router.delete(destroyGoal.url(props.goal.id), {
        onSuccess: () => router.visit(index.url()),
    });
}
</script>

<template>
    <Head :title="goal.name" />

    <div class="flex h-full flex-1 flex-col rounded-xl p-4 md:px-6 md:pb-6">
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-4">
            <PageSectionCard>
                <div class="flex items-start justify-between gap-4">
                    <PageTitleOrnament
                        :title="goal.name"
                        :subtitle="goal.why ?? undefined"
                        align="left"
                    />
                    <Link
                        :href="index()"
                        class="mt-2 flex shrink-0 items-center gap-2 rounded-full border border-cd-line px-3.5 py-1.5 font-sans text-sm text-cd-ink-muted transition-colors hover:border-primary/30 hover:bg-primary-hover hover:text-primary"
                    >
                        <ArrowLeft
                            :size="16"
                            :stroke-width="1.6"
                            aria-hidden="true"
                        />
                        目標一覧へ戻る
                    </Link>
                </div>

                <dl
                    class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 font-sans text-sm md:grid-cols-4"
                >
                    <div>
                        <dt class="text-cd-ink-muted">状態</dt>
                        <dd class="font-semibold text-cd-ink">
                            {{ statusLabels[goal.status] }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-cd-ink-muted">期日</dt>
                        <dd class="font-semibold text-cd-ink">
                            {{ goal.deadline ?? '—' }}
                        </dd>
                    </div>
                    <div v-if="goal.parent">
                        <dt class="text-cd-ink-muted">親目標</dt>
                        <dd class="font-semibold text-cd-ink">
                            {{ goal.parent.name }}
                        </dd>
                    </div>
                    <div v-if="goal.matrix_cell?.life_area">
                        <dt class="text-cd-ink-muted">元の領域</dt>
                        <dd class="font-semibold text-cd-ink">
                            {{ goal.matrix_cell.life_area }}
                        </dd>
                    </div>
                </dl>
            </PageSectionCard>

            <PageSectionCard padding="none" aria-label="達成指標">
                <h2
                    class="border-b border-cd-line px-5 py-4 font-sans text-base font-semibold text-cd-ink"
                >
                    達成指標
                </h2>

                <p
                    v-if="(goal.goal_metrics ?? []).length === 0"
                    class="px-5 py-6 text-center font-sans text-sm text-cd-ink-muted"
                >
                    指標ごとの現在地と目標を並べて表示します（合成スコアは作りません）。
                </p>

                <ul v-else class="divide-y divide-cd-line">
                    <li
                        v-for="goalMetric in goal.goal_metrics"
                        :key="goalMetric.id"
                        class="flex items-center gap-3 px-5 py-3"
                    >
                        <span
                            class="min-w-0 truncate font-sans text-sm font-semibold text-cd-ink"
                        >
                            {{
                                goalMetric.metric?.label ?? goalMetric.metric_id
                            }}
                        </span>
                        <span class="font-sans text-sm text-cd-ink-muted">
                            現在
                            {{ goalMetric.baseline_value ?? '—' }}
                            → 目標
                            {{
                                goalMetric.target_value ??
                                (goalMetric.target_low !== null &&
                                goalMetric.target_high !== null
                                    ? `${goalMetric.target_low}〜${goalMetric.target_high}`
                                    : '—')
                            }}
                            {{ goalMetric.metric?.unit ?? '' }}
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="ml-auto"
                            :aria-label="`${goalMetric.metric?.label ?? '指標'} を削除`"
                            @click="removeMetric(goalMetric.id)"
                        >
                            <Trash2 :size="15" :stroke-width="1.6" />
                        </Button>
                    </li>
                </ul>

                <div
                    class="flex flex-wrap items-center gap-2 border-t border-cd-line px-5 py-4"
                >
                    <select
                        v-model="newMetricId"
                        aria-label="追加する指標"
                        class="h-9 rounded-md border border-cd-line bg-transparent px-3 font-sans text-sm text-cd-ink"
                    >
                        <option value="">指標を選択...</option>
                        <option
                            v-for="metric in availableMetrics"
                            :key="metric.id"
                            :value="metric.id"
                        >
                            {{ metric.label }}（{{ metric.unit }}）
                        </option>
                    </select>
                    <Input
                        v-model="newMetricTarget"
                        type="number"
                        step="any"
                        placeholder="目標値"
                        class="w-28"
                        aria-label="目標値"
                    />
                    <Button
                        type="button"
                        size="sm"
                        :disabled="newMetricId === ''"
                        @click="addMetric"
                    >
                        <Plus :size="15" :stroke-width="1.8" />
                        指標を追加
                    </Button>
                </div>
            </PageSectionCard>

            <PageSectionCard aria-label="状態の変更">
                <h2 class="mb-3 font-sans text-base font-semibold text-cd-ink">
                    状態の変更
                </h2>
                <div class="flex flex-wrap items-center gap-2">
                    <select
                        v-model="editingStatus"
                        aria-label="状態"
                        class="h-9 rounded-md border border-cd-line bg-transparent px-3 font-sans text-sm text-cd-ink"
                    >
                        <option
                            v-for="(label, value) in statusLabels"
                            :key="value"
                            :value="value"
                        >
                            {{ label }}
                        </option>
                    </select>
                    <Input
                        v-model="editReason"
                        placeholder="変更理由（必須）"
                        maxlength="500"
                        class="min-w-48 flex-1"
                        aria-label="変更理由"
                    />
                    <Button
                        type="button"
                        size="sm"
                        :disabled="editReason.trim() === ''"
                        @click="saveStatus"
                    >
                        保存
                    </Button>
                </div>
                <p class="mt-2 font-sans text-xs text-cd-ink-muted">
                    目標の変更は理由つきで履歴に記録されます。
                </p>
            </PageSectionCard>

            <PageSectionCard
                v-if="(goal.programs ?? []).length > 0"
                padding="none"
                aria-label="関連プログラム"
            >
                <h2
                    class="border-b border-cd-line px-5 py-4 font-sans text-base font-semibold text-cd-ink"
                >
                    関連プログラム
                </h2>
                <ul class="divide-y divide-cd-line">
                    <li
                        v-for="program in goal.programs"
                        :key="program.id"
                        class="px-5 py-3"
                    >
                        <Link
                            :href="showProgram(program.id)"
                            class="font-sans text-sm font-semibold text-cd-ink hover:text-primary"
                        >
                            {{ program.name }}
                        </Link>
                    </li>
                </ul>
            </PageSectionCard>

            <PageSectionCard
                v-if="(goal.change_logs ?? []).length > 0"
                padding="none"
                aria-label="変更履歴"
            >
                <h2
                    class="border-b border-cd-line px-5 py-4 font-sans text-base font-semibold text-cd-ink"
                >
                    変更履歴
                </h2>
                <ul class="divide-y divide-cd-line">
                    <li
                        v-for="log in goal.change_logs"
                        :key="log.id"
                        class="px-5 py-3"
                    >
                        <p class="font-sans text-sm text-cd-ink">
                            {{ log.reason ?? '（理由なし）' }}
                        </p>
                        <p class="mt-0.5 font-sans text-xs text-cd-ink-muted">
                            {{
                                new Date(log.created_at).toLocaleString('ja-JP')
                            }}
                        </p>
                    </li>
                </ul>
            </PageSectionCard>

            <PageSectionCard aria-label="危険な操作">
                <div class="flex items-center justify-between gap-4">
                    <p class="font-sans text-sm text-cd-ink-muted">
                        この目標を削除する（関連プログラムは残ります）
                    </p>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="text-destructive hover:text-destructive"
                        @click="removeGoal"
                    >
                        <Trash2 :size="15" :stroke-width="1.6" />
                        削除
                    </Button>
                </div>
            </PageSectionCard>
        </div>
    </div>
</template>
