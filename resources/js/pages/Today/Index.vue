<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CirclePlay, Plus } from '@lucide/vue';
import { ref } from 'vue';
import DateNavigator from '@/components/DateNavigator.vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import RoutinesHubTabs from '@/components/routine/RoutinesHubTabs.vue';
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
import { routinePlanStatusLabels } from '@/lib/routineConstants';
import type { RoutinePlan } from '@/types/routine';

interface Props {
    date: string;
    plans: RoutinePlan[];
}

const props = defineProps<Props>();

const showCreateModal = ref(false);
const formTitle = ref('');
const saving = ref(false);

function latestSession(plan: RoutinePlan) {
    return plan.sessions?.[0] ?? null;
}

async function createPlan(): Promise<void> {
    if (!formTitle.value.trim()) {
        return;
    }

    saving.value = true;

    try {
        await apiFetch('/plans', {
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
    <Head title="今日の実行プラン" />

    <div
        class="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4 md:px-6 md:pb-6"
    >
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6">
            <div class="flex items-start justify-between gap-4">
                <PageTitleOrnament
                    title="今日の実行プラン"
                    subtitle="スケジュールされた実行プランを確認・実行します。"
                    align="left"
                />
            </div>

            <RoutinesHubTabs />

            <DateNavigator :date="date" route-url="/today" :reload-only="['plans', 'date']">
                <template #actions>
                    <Button
                        type="button"
                        class="shrink-0 font-sans tracking-[0.08em]"
                        @click="showCreateModal = true"
                    >
                        <Plus :size="16" :stroke-width="1.8" />
                        追加
                    </Button>
                </template>
            </DateNavigator>

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
                                    :href="`/plans/${plan.id}`"
                                    class="font-serif text-base tracking-[0.08em] text-cd-ink hover:text-primary"
                                >
                                    {{ plan.title }}
                                </Link>
                                <p
                                    class="mt-1 font-sans text-xs text-cd-ink-muted"
                                >
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
                                    latestSession(plan)?.status === 'completed'
                                        ? 'bg-cd-moss/15 text-cd-moss'
                                        : latestSession(plan)?.status ===
                                            'in_progress'
                                          ? 'bg-cd-sunrise/15 text-cd-sunrise'
                                          : plan.status === 'ready'
                                            ? 'bg-primary/10 text-primary'
                                            : 'bg-muted text-cd-ink-muted'
                                "
                            >
                                {{
                                    latestSession(plan)?.status === 'in_progress'
                                        ? '実行中'
                                        : latestSession(plan)?.status ===
                                            'completed'
                                          ? '完了済み'
                                          : routinePlanStatusLabels[plan.status]
                                }}
                            </span>
                        </div>

                        <div class="mt-3">
                            <Link
                                v-if="
                                    latestSession(plan)?.status === 'in_progress'
                                "
                                :href="`/sessions/${latestSession(plan)!.id}`"
                                class="inline-flex items-center gap-1.5 rounded-full border border-primary/30 bg-primary/10 px-3 py-1 font-sans text-xs tracking-[0.06em] text-primary"
                            >
                                <CirclePlay :size="14" :stroke-width="1.6" />
                                続ける
                            </Link>
                            <Link
                                v-else
                                :href="`/plans/${plan.id}`"
                                class="inline-flex items-center gap-1.5 rounded-full border border-cd-line/80 px-3 py-1 font-sans text-xs tracking-[0.06em] text-cd-ink-muted hover:text-cd-ink"
                            >
                                編集・開始
                            </Link>
                        </div>
                    </li>
                </ul>

                <div
                    v-else
                    class="px-5 py-12 text-center font-sans text-sm text-cd-ink-muted"
                >
                    <p>この日の実行プランはありません。</p>
                    <p class="mt-2">
                        <Link
                            href="/routines"
                            class="text-primary underline-offset-2 hover:underline"
                        >
                            ルーティン
                        </Link>
                        から追加するか、上の「追加」ボタンで新規作成してください。
                    </p>
                </div>
            </section>
        </div>
    </div>

    <Dialog :open="showCreateModal" @update:open="(v) => (showCreateModal = v)">
        <DialogContent class="bg-cd-surface sm:max-w-md">
            <DialogHeader>
                <DialogTitle
                    class="font-serif text-lg tracking-[0.12em] text-cd-ink"
                >
                    実行プランを追加
                </DialogTitle>
            </DialogHeader>

            <Input
                v-model="formTitle"
                placeholder="プラン名"
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
