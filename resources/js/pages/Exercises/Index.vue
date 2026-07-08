<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { apiFetch } from '@/lib/apiFetch';
import {
    exerciseCategoryLabels,
    trackingTypeLabels,
} from '@/lib/trainingConstants';
import type {
    Exercise,
    ExerciseCategory,
    TrackingType,
} from '@/types/training';

interface Props {
    exercises: Exercise[];
}

const props = defineProps<Props>();

const showModal = ref(false);
const editingExercise = ref<Exercise | null>(null);
const formName = ref('');
const formCategory = ref<ExerciseCategory>('strength');
const formTrackingType = ref<TrackingType>('weight_reps');
const formNote = ref('');
const saving = ref(false);
const formError = ref<string | null>(null);

const groupedExercises = computed(() => {
    const groups = new Map<ExerciseCategory, Exercise[]>();

    for (const exercise of props.exercises) {
        const list = groups.get(exercise.category) ?? [];
        list.push(exercise);
        groups.set(exercise.category, list);
    }

    return (Object.keys(exerciseCategoryLabels) as ExerciseCategory[])
        .filter((category) => groups.has(category))
        .map((category) => ({
            category,
            label: exerciseCategoryLabels[category],
            items: groups.get(category) ?? [],
        }));
});

function openCreate(): void {
    editingExercise.value = null;
    formName.value = '';
    formCategory.value = 'strength';
    formTrackingType.value = 'weight_reps';
    formNote.value = '';
    formError.value = null;
    showModal.value = true;
}

function openEdit(exercise: Exercise): void {
    editingExercise.value = exercise;
    formName.value = exercise.name;
    formCategory.value = exercise.category;
    formTrackingType.value = exercise.tracking_type;
    formNote.value = exercise.note ?? '';
    formError.value = null;
    showModal.value = true;
}

async function saveExercise(): Promise<void> {
    if (!formName.value.trim()) {
        return;
    }

    saving.value = true;
    formError.value = null;

    const payload = {
        name: formName.value.trim(),
        category: formCategory.value,
        tracking_type: formTrackingType.value,
        note: formNote.value.trim() || null,
    };

    try {
        if (editingExercise.value) {
            await apiFetch(`/exercises/${editingExercise.value.id}`, {
                method: 'PATCH',
                body: JSON.stringify(payload),
            });
        } else {
            await apiFetch('/exercises', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
        }

        showModal.value = false;
        router.reload({ only: ['exercises'] });
    } catch {
        formError.value = '保存に失敗しました。';
    } finally {
        saving.value = false;
    }
}

async function deleteExercise(exercise: Exercise): Promise<void> {
    if (!confirm(`「${exercise.name}」を削除しますか？`)) {
        return;
    }

    await apiFetch(`/exercises/${exercise.id}`, { method: 'DELETE' });
    router.reload({ only: ['exercises'] });
}
</script>

<template>
    <Head title="種目" />

    <div
        class="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4 md:px-6 md:pb-6"
    >
        <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6">
            <div class="flex items-start justify-between gap-4">
                <PageTitleOrnament
                    title="種目"
                    subtitle="トレーニングで使う種目をカテゴリ別に管理します。"
                    align="left"
                />

                <Button
                    type="button"
                    class="mt-2 shrink-0 font-sans tracking-[0.08em]"
                    @click="openCreate"
                >
                    <Plus :size="16" :stroke-width="1.8" />
                    追加
                </Button>
            </div>

            <RoutinesHubTabs />

            <section
                v-for="group in groupedExercises"
                :key="group.category"
                :aria-label="group.label"
                class="cd-shadow-soft rounded-2xl border border-cd-line bg-cd-surface"
            >
                <h2
                    class="border-b border-cd-line/60 px-5 py-4 font-serif text-base tracking-[0.12em] text-cd-ink"
                >
                    {{ group.label }}
                </h2>
                <ul class="flex flex-col">
                    <li
                        v-for="exercise in group.items"
                        :key="exercise.id"
                        class="flex items-center justify-between gap-3 border-b border-cd-line/60 px-5 py-4 last:border-b-0"
                        :class="{ 'opacity-55': !exercise.is_active }"
                    >
                        <div class="min-w-0">
                            <p
                                class="truncate font-serif text-base tracking-[0.08em] text-cd-ink"
                            >
                                {{ exercise.name }}
                            </p>
                            <p
                                class="mt-0.5 font-sans text-xs text-cd-ink-muted"
                            >
                                {{ trackingTypeLabels[exercise.tracking_type] }}
                                <span
                                    v-if="exercise.videos_count"
                                    class="before:mx-1.5 before:content-['·']"
                                >
                                    動画 {{ exercise.videos_count }}
                                </span>
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :aria-label="`${exercise.name} を編集`"
                                @click="openEdit(exercise)"
                            >
                                <Pencil :size="15" :stroke-width="1.6" />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :aria-label="`${exercise.name} を削除`"
                                @click="deleteExercise(exercise)"
                            >
                                <Trash2 :size="15" :stroke-width="1.6" />
                            </Button>
                        </div>
                    </li>
                </ul>
            </section>

            <p
                v-if="exercises.length === 0"
                class="py-12 text-center font-sans text-sm text-cd-ink-muted"
            >
                種目がまだありません。
            </p>
        </div>
    </div>

    <Dialog :open="showModal" @update:open="(v) => (showModal = v)">
        <DialogContent class="bg-cd-surface sm:max-w-md">
            <DialogHeader>
                <DialogTitle
                    class="font-serif text-lg tracking-[0.12em] text-cd-ink"
                >
                    {{ editingExercise ? '種目を編集' : '種目を追加' }}
                </DialogTitle>
            </DialogHeader>

            <div class="flex flex-col gap-3">
                <Input
                    v-model="formName"
                    placeholder="種目名"
                    maxlength="100"
                    :disabled="saving"
                />

                <Select v-model="formCategory" :disabled="saving">
                    <SelectTrigger>
                        <SelectValue placeholder="カテゴリ" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="(label, value) in exerciseCategoryLabels"
                            :key="value"
                            :value="value"
                        >
                            {{ label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select v-model="formTrackingType" :disabled="saving">
                    <SelectTrigger>
                        <SelectValue placeholder="記録形式" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="(label, value) in trackingTypeLabels"
                            :key="value"
                            :value="value"
                        >
                            {{ label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Input
                    v-model="formNote"
                    placeholder="メモ（任意）"
                    :disabled="saving"
                />

                <p v-if="formError" class="font-sans text-xs text-destructive">
                    {{ formError }}
                </p>
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="ghost"
                    :disabled="saving"
                    @click="showModal = false"
                >
                    キャンセル
                </Button>
                <Button
                    type="button"
                    :disabled="saving || !formName.trim()"
                    @click="saveExercise"
                >
                    保存
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
