<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    CirclePlay,
    Plus,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import RoutinesHubTabs from '@/components/training/RoutinesHubTabs.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { apiFetch } from '@/lib/apiFetch';
import { trainingPlanStatusLabels } from '@/lib/trainingConstants';
import type { TrainingPlan } from '@/types/training';

interface Props {
    date: string;
    plans: TrainingPlan[];
}

const props = defineProps<Props>();

const showCreateModal = ref(false);
const formTitle = ref('');
const saving = ref(false);

const formattedDate = computed(() => {
    const d = new Date(`${props.date}T00:00:00`);

    return d.toLocaleDateString('ja-JP', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        weekday: 'short',
    });
});

const isToday = computed(() => props.date === new Date().toISOString().slice(0, 10));

function shiftDate(days: number): void {
    const current = new Date(`${props.date}T00:00:00`);
    current.setDate(current.getDate() + days);

    router.get(
        '/training',
        { date: current.toISOString().slice(0, 10) },
        { preserveState: true, preserveScroll: true },
    );
}

function goToday(): void {
    router.get('/training', {}, { preserveState: true, preserveScroll: true });
}

function latestRun(plan: TrainingPlan) {
    return plan.runs?.[0] ?? null;
}

async function createPlan(): Promise<void> {
    if (!formTitle.value.trim()) {
        return;
    }

    saving.value = true;

    try {
        await apiFetch('/training/plans', {
            method: 'POST',
            body: JSON.stringify({
                title: formTitle.value.trim(),
                scheduled_on: props.date,
            }),
        });

        showCreateModal.value = false;
        formTitle.value = '';
        router.reload({ only: ['plans', 'date'] });
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Head title="今日のメニュー" />

    <div
        class="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4 md:px-6 md:pb-6"
    >
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6">
            <div class="flex items-start justify-between gap-4">
                <PageTitleOrnament
                    title="今日のメニュー"
                    subtitle="スケジュールされたトレーニングプランを確認・実行します。"
                    align="left"
                />

                <Button
                    type="button"
                    class="mt-2 shrink-0 font-sans tracking-[0.08em]"
                    @click="showCreateModal = true"
                >
                    <Plus :size="16" :stroke-width="1.8" />
                    追加
                </Button>
            </div>

            <RoutinesHubTabs />

            <div
                class="flex items-center justify-between gap-3 rounded-2xl border border-cd-line/80 bg-white/60 px-4 py-3"
            >
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="前の日"
                    @click="shiftDate(-1)"
                >
                    <ChevronLeft :size="18" :stroke-width="1.6" />
                </Button>

                <div class="text-center">
                    <p
                        class="font-serif text-base tracking-[0.1em] text-cd-ink"
                    >
                        {{ formattedDate }}
                    </p>
                    <button
                        v-if="!isToday"
                        type="button"
                        class="mt-0.5 font-sans text-xs text-primary underline-offset-2 hover:underline"
                        @click="goToday"
                    >
                        今日に戻る
                    </button>
                </div>

                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="次の日"
                    @click="shiftDate(1)"
                >
                    <ChevronRight :size="18" :stroke-width="1.6" />
                </Button>
            </div>

            <section
                aria-label="プラン一覧"
                class="cd-shadow-soft rounded-2xl border border-cd-line bg-cd-surface"
            >
                <ul v-if="plans.length > 0" class="flex flex-col">
                    <li
                        v-for="plan in plans"
                        :key="plan.id"
                        class="border-b border-cd-line/60 px-5 py-4 last:border-b-0"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <Link
                                    :href="`/training/plans/${plan.id}`"
                                    class="font-serif text-base tracking-[0.08em] text-cd-ink hover:text-primary"
                                >
                                    {{ plan.title }}
                                </Link>
                                <p class="mt-1 font-sans text-xs text-cd-ink-muted">
                                    {{ plan.steps?.length ?? 0 }} ステップ
                                    <span
                                        v-if="plan.life_area"
                                        class="before:mx-1.5 before:content-['·']"
                                    >
                                        {{ plan.life_area.name }}
                                    </span>
                                </p>
                            </div>

                            <span
                                class="inline-flex shrink-0 rounded-full px-2.5 py-0.5 font-sans text-xs"
                                :class="
                                    latestRun(plan)?.status === 'completed'
                                        ? 'bg-cd-moss/15 text-cd-moss'
                                        : latestRun(plan)?.status ===
                                            'in_progress'
                                          ? 'bg-cd-sunrise/15 text-cd-sunrise'
                                          : plan.status === 'ready'
                                            ? 'bg-primary/10 text-primary'
                                            : 'bg-muted text-cd-ink-muted'
                                "
                            >
                                {{
                                    latestRun(plan)?.status === 'in_progress'
                                        ? '実行中'
                                        : latestRun(plan)?.status ===
                                            'completed'
                                          ? '完了済み'
                                          : trainingPlanStatusLabels[plan.status]
                                }}
                            </span>
                        </div>

                        <div class="mt-3">
                            <Link
                                v-if="latestRun(plan)?.status === 'in_progress'"
                                :href="`/training/runs/${latestRun(plan)!.id}`"
                                class="inline-flex items-center gap-1.5 rounded-full border border-primary/30 bg-primary/10 px-3 py-1 font-sans text-xs tracking-[0.06em] text-primary"
                            >
                                <CirclePlay :size="14" :stroke-width="1.6" />
                                続ける
                            </Link>
                            <Link
                                v-else
                                :href="`/training/plans/${plan.id}`"
                                class="inline-flex items-center gap-1.5 rounded-full border border-cd-line/80 px-3 py-1 font-sans text-xs tracking-[0.06em] text-cd-ink-muted hover:text-cd-ink"
                            >
                                編集・開始
                            </Link>
                        </div>
                    </li>
                </ul>

                <p
                    v-else
                    class="px-5 py-12 text-center font-sans text-sm text-cd-ink-muted"
                >
                    この日のメニューはありません。
                </p>
            </section>
        </div>
    </div>

    <Dialog :open="showCreateModal" @update:open="(v) => (showCreateModal = v)">
        <DialogContent class="bg-cd-surface sm:max-w-md">
            <DialogHeader>
                <DialogTitle
                    class="font-serif text-lg tracking-[0.12em] text-cd-ink"
                >
                    メニューを追加
                </DialogTitle>
            </DialogHeader>

            <Input
                v-model="formTitle"
                placeholder="メニュー名"
                maxlength="100"
                :disabled="saving"
            />

            <DialogFooter>
                <Button
                    type="button"
                    variant="ghost"
                    :disabled="saving"
                    @click="showCreateModal = false"
                >
                    キャンセル
                </Button>
                <Button
                    type="button"
                    :disabled="saving || !formTitle.trim()"
                    @click="createPlan"
                >
                    作成
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
