<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarPlus, ChevronRight, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
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
import { todayKey } from '@/lib/date';
import type { Routine } from '@/types/routine';

interface Props {
    routines: Routine[];
}

defineProps<Props>();

const showCreateModal = ref(false);
const formName = ref('');
const formDescription = ref('');
const saving = ref(false);
const applyingId = ref<string | null>(null);

function todayString(): string {
    return todayKey();
}

async function createRoutine(): Promise<void> {
    if (!formName.value.trim()) {
        return;
    }

    saving.value = true;

    try {
        await apiFetch('/routines', {
            method: 'POST',
            body: JSON.stringify({
                name: formName.value.trim(),
                description: formDescription.value.trim() || null,
            }),
        });

        showCreateModal.value = false;
        formName.value = '';
        formDescription.value = '';
        router.reload({ only: ['routines'] });
    } finally {
        saving.value = false;
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
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6">
            <div class="flex items-start justify-between gap-4">
                <PageTitleOrnament
                    title="ルーティン"
                    subtitle="再利用できるルーティンを管理します。"
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

            <section
                aria-label="ルーティン一覧"
                class="cd-shadow-soft rounded-2xl border border-cd-line bg-cd-surface"
            >
                <ul v-if="routines.length > 0" class="flex flex-col">
                    <li
                        v-for="routine in routines"
                        :key="routine.id"
                        class="border-b border-cd-line/60 px-5 py-4 last:border-b-0"
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
                                        class="truncate font-serif text-base tracking-[0.08em] text-cd-ink group-hover:text-primary"
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
                                    class="mt-1 line-clamp-2 font-sans text-xs text-cd-ink-muted"
                                >
                                    {{ routine.description }}
                                </p>
                                <p
                                    class="mt-1 font-sans text-xs text-cd-ink-muted"
                                >
                                    {{ routine.steps_count ?? 0 }} ステップ
                                </p>
                            </div>

                            <div class="flex shrink-0 flex-wrap gap-2">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    class="font-sans text-xs tracking-[0.06em]"
                                    :disabled="applyingId === routine.id"
                                    @click="applyToToday(routine)"
                                >
                                    <CalendarPlus
                                        :size="14"
                                        :stroke-width="1.6"
                                    />
                                    今日の実行にする
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
                    class="px-5 py-12 text-center font-sans text-sm text-cd-ink-muted"
                >
                    <p>ルーティンがまだありません。</p>
                    <p class="mt-2">
                        上の「追加」ボタンから、繰り返し使うルーティンを作成しましょう。
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
                    ルーティンを追加
                </DialogTitle>
            </DialogHeader>

            <div class="flex flex-col gap-3">
                <Input
                    v-model="formName"
                    placeholder="ルーティン名"
                    maxlength="100"
                    :disabled="saving"
                />
                <Input
                    v-model="formDescription"
                    placeholder="説明（任意）"
                    :disabled="saving"
                />
            </div>

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
                    :disabled="saving || !formName.trim()"
                    @click="createRoutine"
                >
                    作成
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
