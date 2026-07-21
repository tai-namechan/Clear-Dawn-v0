<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    Pencil,
    Plus,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import RoutineBasicsForm from '@/components/forms/RoutineBasicsForm.vue';
import PageSectionCard from '@/components/PageSectionCard.vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
import ReorderableList from '@/components/ReorderableList.vue';
import RoutineEditorSidebar from '@/components/routine/RoutineEditorSidebar.vue';
import RoutinesHubTabs from '@/components/routine/RoutinesHubTabs.vue';
import StepEditorDialog from '@/components/routine/StepEditorDialog.vue';
import type { StepEditorPayload } from '@/components/routine/StepEditorDialog.vue';
import { Button } from '@/components/ui/button';
import { apiFetch, ApiError } from '@/lib/apiFetch';
import { ensureArray } from '@/lib/array';
import { todayKey } from '@/lib/date';
import { fetchRoutineItemsFromPage } from '@/lib/fetchRoutineItems';
import {
    estimateStepDurationSeconds,
    formatDurationSeconds,
    formatStepTarget,
    resolveStepPurpose,
    routineItemCategoryLabels,
    stepPurposeLabels,
    trackingTypeLabels,
} from '@/lib/routineConstants';
import { purposeChipClasses } from '@/lib/stepPurposeColors';
import type { LifeArea } from '@/types/matrix';
import type {
    Routine,
    RoutineEditor,
    RoutineItem,
    RoutineStep,
    Video,
} from '@/types/routine';

interface Props {
    routine: RoutineEditor;
    lifeAreas: LifeArea[];
    otherRoutines: Routine[];
    routineItems?: RoutineItem[];
    videos?: Video[];
    isCreating?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    routineItems: () => [],
    videos: () => [],
    isCreating: false,
});

const formName = ref(props.routine.name);
const formDescription = ref(props.routine.description ?? '');
const formLifeAreaId = ref<string | null>(props.routine.life_area_id);
const savingRoutine = ref(false);
const savingStep = ref(false);
const applyingToToday = ref(false);
const showAddStepModal = ref(false);
const editingStep = ref<RoutineStep | null>(null);
const formError = ref<string | null>(null);
const routineItems = ref<RoutineItem[]>([...props.routineItems]);
const stepEditorRef = ref<{
    applyApiErrors: (error: unknown) => void;
    clearFieldErrors: () => void;
} | null>(null);

watch(
    () => props.routineItems,
    (items) => {
        routineItems.value = [...items];
    },
);

const steps = computed(() => ensureArray(props.routine.steps));
const isCreateMode = computed(() => props.isCreating || props.routine.id === null);

/** ①名前 → ②ステップ → ③今日/作戦 */
const flowPhase = computed<'name' | 'steps' | 'ready'>(() => {
    if (isCreateMode.value) {
        return 'name';
    }

    if (steps.value.length === 0) {
        return 'steps';
    }

    return 'ready';
});

const showBasics = ref(isCreateMode.value || flowPhase.value !== 'ready');

watch(flowPhase, (phase) => {
    if (phase !== 'ready') {
        showBasics.value = true;
    }
});

const pageHeading = computed(() =>
    isCreateMode.value ? 'ルーティンを作成' : 'ルーティンを編集',
);

const pageSubtitle = computed(() => {
    if (flowPhase.value === 'name') {
        return '① まず名前を入力して保存します。保存後にステップを追加できます。';
    }

    if (flowPhase.value === 'steps') {
        return '② 「ステップを追加」でやることを登録し、ダイアログ内で保存します。';
    }

    return '③ ステップが揃ったら「今日/作戦」へ進めます。基本情報の変更もこの画面で保存できます。';
});

const documentTitle = computed(() =>
    isCreateMode.value
        ? pageHeading.value
        : `${props.routine.name} · ${pageHeading.value}`,
);

const dominantCategory = computed(() => {
    const counts = new Map<string, number>();

    for (const step of steps.value) {
        const category = step.routine_item?.category;

        if (!category) {
            continue;
        }

        counts.set(category, (counts.get(category) ?? 0) + 1);
    }

    const top = [...counts.entries()].sort((a, b) => b[1] - a[1])[0];

    return top
        ? routineItemCategoryLabels[
              top[0] as keyof typeof routineItemCategoryLabels
          ]
        : '—';
});

const totalDurationSeconds = computed(() =>
    steps.value.reduce(
        (sum, step) =>
            sum +
            estimateStepDurationSeconds({
                target_blocks: step.target_blocks,
                target_amount: step.target_amount,
                amount_unit: step.amount_unit,
                rest_seconds: step.rest_seconds,
                tracking_type: step.routine_item?.tracking_type,
            }),
        0,
    ),
);

watch(
    () => props.routine,
    (routine) => {
        formName.value = routine.name;
        formDescription.value = routine.description ?? '';
        formLifeAreaId.value = routine.life_area_id;
    },
);

async function loadRoutineItems(): Promise<void> {
    try {
        routineItems.value = await fetchRoutineItemsFromPage();
    } catch {
        // Keep current list if auxiliary fetch fails (e.g. temporary 409).
    }
}

async function openAddStep(): Promise<void> {
    if (isCreateMode.value || !props.routine.id) {
        return;
    }

    editingStep.value = null;

    // Prefer props already on the page; refresh in background when possible.
    if (routineItems.value.length === 0) {
        await loadRoutineItems();
    } else {
        void loadRoutineItems();
    }

    showAddStepModal.value = true;
}

async function openEditStep(step: RoutineStep): Promise<void> {
    if (isCreateMode.value || !props.routine.id) {
        return;
    }

    editingStep.value = step;

    if (routineItems.value.length === 0) {
        await loadRoutineItems();
    } else {
        void loadRoutineItems();
    }

    showAddStepModal.value = true;
}

function onStepDialogOpen(open: boolean): void {
    showAddStepModal.value = open;

    if (!open) {
        editingStep.value = null;
    }
}

/**
 * 一覧の「今日/作戦」と同じく、今日のプランを作成してから /today へ進む。
 * 遷移だけの Link だと登録されず空の今日/作戦画面になる。
 */
async function applyToToday(): Promise<void> {
    if (!props.routine.id || steps.value.length < 1 || applyingToToday.value) {
        return;
    }

    applyingToToday.value = true;

    try {
        await apiFetch('/plans', {
            method: 'POST',
            body: JSON.stringify({
                title: formName.value.trim() || props.routine.name,
                scheduled_on: todayKey(),
                routine_id: props.routine.id,
            }),
        });

        router.visit('/today');
    } catch {
        formError.value =
            '今日/作戦への登録に失敗しました。もう一度お試しください。';
    } finally {
        applyingToToday.value = false;
    }
}

async function saveRoutine(): Promise<void> {
    const name = formName.value.trim();

    if (!name) {
        formError.value = 'ルーティン名を入力してください。';

        return;
    }

    formError.value = null;
    savingRoutine.value = true;

    try {
        if (isCreateMode.value || !props.routine.id) {
            const result = await apiFetch<{ routine: { id: string } }>(
                '/routines',
                {
                    method: 'POST',
                    body: JSON.stringify({
                        name,
                        description: formDescription.value.trim() || null,
                        life_area_id: formLifeAreaId.value,
                    }),
                },
            );

            router.visit(`/routines/${result.routine.id}`);

            return;
        }

        await apiFetch(`/routines/${props.routine.id}`, {
            method: 'PATCH',
            body: JSON.stringify({
                name,
                description: formDescription.value.trim() || null,
                life_area_id: formLifeAreaId.value,
            }),
        });

        router.reload({ only: ['routine'] });
    } catch (error) {
        formError.value =
            error instanceof ApiError
                ? '保存に失敗しました。入力内容を確認してください。'
                : '保存に失敗しました。もう一度お試しください。';
    } finally {
        savingRoutine.value = false;
    }
}

async function addStep(payload: StepEditorPayload): Promise<void> {
    if (!props.routine.id) {
        return;
    }

    savingStep.value = true;
    stepEditorRef.value?.clearFieldErrors();

    try {
        if (editingStep.value) {
            await apiFetch(
                `/routines/${props.routine.id}/steps/${editingStep.value.id}`,
                {
                    method: 'PATCH',
                    body: JSON.stringify(payload),
                },
            );
        } else {
            await apiFetch(`/routines/${props.routine.id}/steps`, {
                method: 'POST',
                body: JSON.stringify(payload),
            });
        }

        showAddStepModal.value = false;
        editingStep.value = null;
        router.reload({ only: ['routine', 'routineItems'] });
    } catch (error) {
        stepEditorRef.value?.applyApiErrors(error);

        if (!(error instanceof ApiError) || error.status >= 500) {
            // Keep dialog open; field/general errors already shown.
            console.error(error);
        }
    } finally {
        savingStep.value = false;
    }
}

async function deleteStep(step: RoutineStep): Promise<void> {
    if (!props.routine.id) {
        return;
    }

    if (!confirm('このステップを削除しますか？')) {
        return;
    }

    await apiFetch(`/routines/${props.routine.id}/steps/${step.id}`, {
        method: 'DELETE',
    });
    router.reload({ only: ['routine'] });
}

function stepPurpose(step: RoutineStep): string {
    return stepPurposeLabels[
        resolveStepPurpose(step.purpose, step.routine_item?.category ?? null)
    ];
}

function stepPurposeKey(step: RoutineStep) {
    return resolveStepPurpose(
        step.purpose,
        step.routine_item?.category ?? null,
    );
}
</script>

<template>
    <Head :title="documentTitle" />

    <div class="flex min-h-0 flex-1 flex-col rounded-xl p-4 md:px-6 md:pb-6">
        <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-5">
            <PageSectionCard>
                <div class="flex flex-col gap-4">
                    <Link
                        href="/routines"
                        class="inline-flex items-center gap-2 font-sans text-sm font-medium text-cd-ink-muted transition-colors hover:text-primary"
                    >
                        <ArrowLeft :size="16" :stroke-width="1.6" />
                        ルーティン一覧
                    </Link>

                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <PageTitleOrnament
                                :title="isCreateMode ? pageHeading : formName"
                                :subtitle="pageSubtitle"
                                align="left"
                            />
                            <div v-if="!isCreateMode" class="mt-3 flex flex-wrap gap-2">
                                <span class="rounded-full bg-primary/8 px-3 py-1 font-sans text-xs font-medium text-primary">
                                    {{ steps.length }} STEP
                                </span>
                                <span class="rounded-full bg-white px-3 py-1 font-sans text-xs text-cd-ink-muted">
                                    {{ formatDurationSeconds(totalDurationSeconds) }}
                                </span>
                                <span class="rounded-full bg-white px-3 py-1 font-sans text-xs text-cd-ink-muted">
                                    {{ dominantCategory }}
                                </span>
                            </div>
                        </div>

                        <div v-if="flowPhase === 'ready'" class="flex flex-wrap gap-2">
                            <Button type="button" variant="outline" @click="showBasics = !showBasics">
                                <Pencil :size="15" :stroke-width="1.6" />
                                {{ showBasics ? '基本情報を閉じる' : '基本情報を編集' }}
                            </Button>
                            <Button type="button" :disabled="applyingToToday" @click="applyToToday">
                                <CalendarDays :size="16" :stroke-width="1.6" />
                                {{ applyingToToday ? '登録中…' : '今日に追加' }}
                            </Button>
                        </div>
                    </div>

                    <ol
                        v-if="flowPhase !== 'ready'"
                        class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:gap-3"
                        aria-label="作成の手順"
                    >
                        <li
                            v-for="(label, index) in ['名前を保存', 'ステップを保存', '今日/作戦']"
                            :key="label"
                            class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 font-sans text-xs font-medium"
                            :class="
                                (index === 0 && flowPhase === 'name') ||
                                (index === 1 && flowPhase === 'steps')
                                    ? 'border-primary/40 bg-primary/10 text-primary'
                                    : 'border-dashed border-cd-line bg-transparent text-cd-ink-muted'
                            "
                        >
                            <span
                                class="inline-flex size-5 items-center justify-center rounded-full text-[0.65rem] font-bold"
                                :class="
                                    (index === 0 && flowPhase === 'name') ||
                                    (index === 1 && flowPhase === 'steps')
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted text-cd-ink-muted'
                                "
                            >
                                {{ index + 1 }}
                            </span>
                            {{ label }}
                        </li>
                    </ol>

                    <RoutinesHubTabs />
                </div>
            </PageSectionCard>

            <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_260px]">
                <div class="flex min-w-0 flex-col gap-5">
                    <section
                        v-if="showBasics"
                        aria-label="基本情報"
                        class="cd-panel px-5 py-5"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="font-sans text-base font-semibold text-cd-ink">
                                基本情報
                            </h2>
                            <button
                                v-if="!isCreateMode && flowPhase === 'ready'"
                                type="button"
                                class="font-sans text-xs text-cd-ink-muted hover:text-primary"
                                @click="showBasics = false"
                            >
                                閉じる
                            </button>
                        </div>

                        <div class="mt-4">
                            <RoutineBasicsForm
                                v-model:name="formName"
                                v-model:description="formDescription"
                                v-model:life-area-id="formLifeAreaId"
                                :life-areas="lifeAreas"
                                :disabled="savingRoutine"
                                :category-label="dominantCategory"
                                :total-duration-label="formatDurationSeconds(totalDurationSeconds)"
                                :step-count-label="`${steps.length} 件`"
                            />
                        </div>

                        <p v-if="formError" class="mt-3 font-sans text-sm text-destructive" role="alert">
                            {{ formError }}
                        </p>

                        <div class="mt-4 flex flex-wrap items-center justify-end gap-2 border-t border-cd-line pt-4">
                            <Button v-if="isCreateMode" type="button" variant="ghost" as-child>
                                <Link href="/routines">キャンセル</Link>
                            </Button>
                            <Button
                                type="button"
                                :disabled="savingRoutine || !formName.trim()"
                                @click="saveRoutine"
                            >
                                {{ isCreateMode ? '名前を保存して次へ' : '変更を保存' }}
                            </Button>
                        </div>
                    </section>

                    <section
                        aria-label="ステップ一覧"
                        class="cd-panel overflow-hidden"
                        :class="{ 'opacity-70': isCreateMode }"
                    >
                        <div class="flex flex-col gap-2 border-b border-cd-line px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <h2 class="font-sans text-base font-semibold text-cd-ink">
                                    セッション構成
                                    <span class="ml-1 font-normal text-cd-ink-muted">({{ steps.length }})</span>
                                </h2>
                                <p class="mt-1 font-sans text-xs text-cd-ink-muted">
                                    DAY内で行う順番です。ドラッグまたは上下ボタンで並べ替えられます。
                                </p>
                            </div>
                            <Button type="button" size="sm" :disabled="isCreateMode" @click="openAddStep">
                                <Plus :size="14" :stroke-width="1.8" />
                                ステップを追加
                            </Button>
                        </div>

                        <ReorderableList
                            v-if="steps.length"
                            :items="steps"
                            :reorder-url="`/routines/${routine.id}/steps/reorder`"
                            :item-label="(step) => step.display_name || step.routine_item?.name"
                        >
                            <template #row="{ item: step, index }">
                                <div class="flex flex-wrap items-start gap-x-3 gap-y-2">
                                    <span class="inline-flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/8 font-sans text-xs font-semibold text-primary">
                                        {{ index + 1 }}
                                    </span>
                                    <div class="min-w-[12rem] flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-sans text-sm font-semibold text-cd-ink sm:text-base">
                                                {{ step.display_name || step.routine_item?.name || '—' }}
                                            </p>
                                            <span
                                                class="inline-flex rounded-full border px-2 py-0.5 font-sans text-[11px]"
                                                :class="purposeChipClasses(stepPurposeKey(step))"
                                            >
                                                {{ stepPurpose(step) }}
                                            </span>
                                        </div>
                                        <p v-if="step.title && step.routine_item?.name" class="mt-0.5 font-sans text-xs text-cd-ink-muted">
                                            実施項目: {{ step.routine_item.name }}
                                        </p>
                                        <p class="mt-1 font-sans text-xs text-cd-ink-muted">
                                            {{ formatStepTarget(step) }}
                                            <span v-if="step.routine_item" class="before:mx-1.5 before:content-['·']">
                                                {{ trackingTypeLabels[step.routine_item.tracking_type] }}
                                            </span>
                                            <span class="before:mx-1.5 before:content-['·']">
                                                {{ formatDurationSeconds(estimateStepDurationSeconds({
                                                    target_blocks: step.target_blocks,
                                                    target_amount: step.target_amount,
                                                    amount_unit: step.amount_unit,
                                                    rest_seconds: step.rest_seconds,
                                                    tracking_type: step.routine_item?.tracking_type,
                                                })) }}
                                            </span>
                                        </p>
                                        <p v-if="step.note || step.video" class="mt-1 line-clamp-1 font-sans text-xs text-cd-ink-muted">
                                            <span v-if="step.note">{{ step.note }}</span>
                                            <span v-if="step.video" class="before:mx-1.5 before:content-['·']">
                                                動画: {{ step.video.title }}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </template>
                            <template #actions="{ item: step }">
                                <Button type="button" variant="ghost" size="icon-sm" aria-label="ステップを編集" @click="openEditStep(step)">
                                    <Pencil :size="14" :stroke-width="1.6" />
                                </Button>
                                <Button type="button" variant="ghost" size="icon-sm" aria-label="ステップを削除" @click="deleteStep(step)">
                                    <Trash2 :size="14" :stroke-width="1.6" />
                                </Button>
                            </template>
                        </ReorderableList>

                        <div v-else class="px-5 py-12 text-center font-sans text-sm text-cd-ink-muted">
                            <template v-if="isCreateMode">
                                <p>基本情報を保存すると、ステップを追加できます。</p>
                            </template>
                            <template v-else>
                                <p>ステップがまだありません。</p>
                                <Button type="button" size="sm" class="mt-4" @click="openAddStep">
                                    <Plus :size="14" :stroke-width="1.8" />
                                    最初のステップを追加
                                </Button>
                            </template>
                        </div>
                    </section>
                </div>

                <RoutineEditorSidebar
                    :routine="routine"
                    :flow-phase="flowPhase"
                    :applying-to-today="applyingToToday"
                    :category-label="dominantCategory"
                    :duration-label="formatDurationSeconds(totalDurationSeconds)"
                    @apply-to-today="applyToToday"
                    @edit-basics="showBasics = true"
                />
            </div>
        </div>
    </div>

    <StepEditorDialog
        v-if="!isCreateMode"
        ref="stepEditorRef"
        :open="showAddStepModal"
        :routine-items="routineItems"
        :videos="videos"
        :saving="savingStep"
        :editing-step="editingStep"
        @update:open="onStepDialogOpen"
        @submit="addStep"
        @items-changed="loadRoutineItems"
    />
</template>
