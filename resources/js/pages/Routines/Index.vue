<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarPlus, ChevronRight, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import PageSectionCard from '@/components/PageSectionCard.vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import RoutinesHubTabs from '@/components/routine/RoutinesHubTabs.vue';
import { Button } from '@/components/ui/button';
import { apiFetch } from '@/lib/apiFetch';
import { todayKey } from '@/lib/date';
import type { Routine } from '@/types/routine';

interface Props {
    routines: Routine[];
}

defineProps<Props>();

const creating = ref(false);
const applyingId = ref<string | null>(null);

function todayString(): string {
    return todayKey();
}

async function createRoutine(): Promise<void> {
    if (creating.value) {
        return;
    }

    creating.value = true;

    try {
        const result = await apiFetch<{ routine: { id: string } }>(
            '/routines',
            {
                method: 'POST',
                body: JSON.stringify({
                    name: '新しいルーティン',
                }),
            },
        );

        router.visit(`/routines/${result.routine.id}`);
    } finally {
        creating.value = false;
    }
}

async function applyToToday(routine: Routine): Promise<void> {
    applyingId.value = routine.id;

    try {
        await apiFetch('/plans', {
            method: 'POST',
            body: JSON.stringify({
                title: routine.name,
                scheduled_on: todayString(),
                routine_id: routine.id,
            }),
        });

        router.visit('/today');
    } finally {
        applyingId.value = null;
    }
}

async function deleteRoutine(routine: Routine): Promise<void> {
    if (!confirm(`「${routine.name}」を削除しますか？`)) {
        return;
    }

    await apiFetch(`/routines/${routine.id}`, { method: 'DELETE' });
    router.reload({ only: ['routines'] });
}
</script>

<template>
    <Head title="ルーティン" />

    <div
        class="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4 md:px-6 md:pb-6"
    >
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-4">
            <PageSectionCard>
                <div class="flex items-start justify-between gap-4">
                    <PageTitleOrnament
                        title="ルーティン"
                        subtitle="ルーティンを作り、ステップを追加してから今日やります。"
                        align="left"
                    />

                    <Button
                        type="button"
                        class="mt-2 shrink-0"
                        :disabled="creating"
                        @click="createRoutine"
                    >
                        <Plus :size="16" :stroke-width="1.8" />
                        ルーティンを作る
                    </Button>
                </div>

                <div class="mt-5">
                    <RoutinesHubTabs />
                </div>
            </PageSectionCard>

            <PageSectionCard padding="none" aria-label="ルーティン一覧">
                <ul v-if="routines.length > 0" class="flex flex-col">
                    <li
                        v-for="routine in routines"
                        :key="routine.id"
                        class="border-b border-cd-line px-5 py-4 last:border-b-0"
                        :class="{ 'opacity-55': !routine.is_active }"
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="min-w-0 flex-1">
                                <Link
                                    :href="`/routines/${routine.id}`"
                                    class="group flex items-center gap-1"
                                >
                                    <p
                                        class="truncate font-sans text-base font-semibold text-cd-ink group-hover:text-primary"
                                    >
                                        {{ routine.name }}
                                    </p>
                                    <ChevronRight
                                        :size="16"
                                        :stroke-width="1.6"
                                        class="shrink-0 text-cd-ink-muted opacity-0 transition-opacity group-hover:opacity-100"
                                    />
                                </Link>
                                <p
                                    v-if="routine.description"
                                    class="mt-1 line-clamp-2 font-sans text-sm text-cd-ink-muted"
                                >
                                    {{ routine.description }}
                                </p>
                                <p class="mt-1 font-sans text-sm text-cd-ink-muted">
                                    {{ routine.steps_count ?? 0 }} ステップ
                                </p>
                            </div>

                            <div class="flex shrink-0 flex-wrap gap-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    :disabled="
                                        applyingId === routine.id ||
                                        (routine.steps_count ?? 0) < 1
                                    "
                                    @click="applyToToday(routine)"
                                >
                                    <CalendarPlus
                                        :size="14"
                                        :stroke-width="1.6"
                                    />
                                    今日やる
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="`${routine.name} を削除`"
                                    @click="deleteRoutine(routine)"
                                >
                                    <Trash2 :size="15" :stroke-width="1.6" />
                                </Button>
                            </div>
                        </div>
                    </li>
                </ul>

                <div
                    v-else
                    class="flex flex-col items-center gap-4 px-5 py-14 text-center"
                >
                    <div class="space-y-2">
                        <p class="font-sans text-base font-semibold text-cd-ink">
                            まだルーティンがありません
                        </p>
                        <p class="max-w-sm font-sans text-sm text-cd-ink-muted">
                            ① ルーティンを作る → ② ステップを追加 → ③ 今日やる、の順で進めます。
                        </p>
                    </div>
                    <Button
                        type="button"
                        :disabled="creating"
                        @click="createRoutine"
                    >
                        <Plus :size="16" :stroke-width="1.8" />
                        ルーティンを作る
                    </Button>
                </div>
            </PageSectionCard>
        </div>
    </div>
</template>
