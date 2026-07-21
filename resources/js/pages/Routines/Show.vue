<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    Clapperboard,
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
        <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6">
            <PageSectionCard>
                <div class="flex flex-col gap-4">
                    <Link
                        href="/routines"
                        class="inline-flex items-center gap-2 font-sans text-sm font-medium text-cd-ink-muted transition-colors hover:text-primary"
                    >
                        <ArrowLeft :size="16" :stroke-width="1.6" />
                        ルーティン一覧
                    </Link>

                    <PageTitleOrnament
                        :title="pageHeading"
                        :subtitle="pageSubtitle"
                        align="left"
                    />

                    <ol
                        class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:gap-3"
                        aria-label="作成の手順"
                    >
                        <li
                            class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 font-sans text-xs font-medium"
                            :class="
                                flowPhase === 'name'
                                    ? 'border-primary/40 bg-primary/10 text-primary'
                                    : 'border-cd-line bg-white text-cd-ink-muted'
                            "
                        >
                            <span
                                class="inline-flex size-5 items-center justify-center rounded-full text-[0.65rem] font-bold"
                                :class="
                                    flowPhase === 'name'
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted text-cd-ink-muted'
                                "
                            >
                                1
                            </span>
                            名前を保存
                        </li>
                        <li
                            class="hidden text-cd-ink-muted sm:inline"
                            aria-hidden="true"
                        >
                            →
                        </li>
                        <li
                            class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 font-sans text-xs font-medium"
                            :class="
                                flowPhase === 'steps'
                                    ? 'border-primary/40 bg-primary/10 text-primary'
                                    : flowPhase === 'ready'
                                      ? 'border-cd-line bg-white text-cd-ink-muted'
                                      : 'border-dashed border-cd-line bg-transparent text-cd-ink-muted'
                            "
                        >
                            <span
                                class="inline-flex size-5 items-center justify-center rounded-full text-[0.65rem] font-bold"
                                :class="
                                    flowPhase === 'steps'
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted text-cd-ink-muted'
                                "
                            >
                                2
                            </span>
                            ステップを保存
                        </li>
                        <li
                            class="hidden text-cd-ink-muted sm:inline"
                            aria-hidden="true"
                        >
                            →
                        </li>
                        <li
                            class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 font-sans text-xs font-medium"
                            :class="
                                flowPhase === 'ready'
                                    ? 'border-primary/40 bg-primary/10 text-primary'
                                    : 'border-dashed border-cd-line bg-transparent text-cd-ink-muted'
                            "
                        >
                            <span
                                class="inline-flex size-5 items-center justify-center rounded-full text-[0.65rem] font-bold"
                                :class="
                                    flowPhase === 'ready'
                                        ? 'bg-primary text-primary-foreground'
                                        : 'bg-muted text-cd-ink-muted'
                                "
                            >
                                3
                            </span>
                            今日/作戦
                        </li>
                    </ol>

                    <RoutinesHubTabs />
                </div>
            </PageSectionCard>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_280px]">
                <div class="flex flex-col gap-6">
                    <section aria-label="基本情報" class="cd-panel px-5 py-5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2
                                class="font-sans text-base font-semibold text-cd-ink"
                            >
                                <span
                                    v-if="isCreateMode"
                                    class="mr-2 inline-flex size-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground"
                                >
                                    1
                                </span>
                                基本情報
                            </h2>
                            <p
                                v-if="isCreateMode"
                                class="font-sans text-xs text-cd-ink-muted"
                            >
                                この段階の保存は「名前」だけです
                            </p>
                        </div>

                        <div class="mt-4">
                            <RoutineBasicsForm
                                v-model:name="formName"
                                v-model:description="formDescription"
                                v-model:life-area-id="formLifeAreaId"
                                :life-areas="lifeAreas"
                                :disabled="savingRoutine"
                                :category-label="dominantCategory"
                                :total-duration-label="
                                    formatDurationSeconds(totalDurationSeconds)
                                "
                                :step-count-label="`${steps.length} 件`"
                            />
                        </div>

                        <p
                            v-if="formError"
                            class="mt-3 font-sans text-sm text-destructive"
                            role="alert"
                        >
                            {{ formError }}
                        </p>

                        <div
                            class="mt-4 flex flex-wrap items-center justify-end gap-2 border-t border-cd-line pt-4"
                        >
                            <Button type="button" variant="ghost" as-child>
                                <Link href="/routines">キャンセル</Link>
                            </Button>
                            <Button
                                type="button"
                                :disabled="savingRoutine || !formName.trim()"
                                @click="saveRoutine"
                            >
                                {{
                                    isCreateMode
                                        ? '① 名前を保存して次へ'
                                        : '基本情報を保存'
                                }}
                            </Button>
                        </div>
                    </section>

                    <section
                        aria-label="ステップ一覧"
                        class="cd-panel overflow-hidden"
                        :class="{ 'opacity-70': isCreateMode }"
                    >
                        <div
                            class="flex flex-col gap-2 border-b border-cd-line px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="min-w-0">
                                <h2
                                    class="font-sans text-base font-semibold text-cd-ink"
                                >
                                    <span
                                        v-if="!isCreateMode"
                                        class="mr-2 inline-flex size-6 items-center justify-center rounded-full text-xs font-bold"
                                        :class="
                                            flowPhase === 'steps'
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-muted text-cd-ink-muted'
                                        "
                                    >
                                        2
                                    </span>
                                    ステップ一覧
                                </h2>
                                <p
                                    v-if="!isCreateMode"
                                    class="mt-1 font-sans text-xs text-cd-ink-muted"
                                >
                                    1件ごとにダイアログで「このステップを保存」します
                                </p>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                :disabled="isCreateMode"
                                @click="openAddStep"
                            >
                                <Plus :size="14" :stroke-width="1.8" />
                                ステップを追加
                            </Button>
                        </div>

                        <div v-if="steps.length" class="overflow-x-auto">
                            <table
                                class="w-full min-w-[880px] text-left font-sans text-sm"
                            >
                                <thead>
                                    <tr
                                        class="border-b border-cd-line/60 bg-white/40 text-xs tracking-[0.06em] text-cd-ink-muted"
                                    >
                                        <th class="px-4 py-3 font-medium">#</th>
                                        <th class="px-4 py-3 font-medium">
                                            項目
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            内容
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            所要時間
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            目的
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            備考
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            動画
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            操作
                                        </th>
                                    </tr>
                                </thead>
                                <ReorderableList
                                    :items="steps"
                                    :reorder-url="`/routines/${routine.id}/steps/reorder`"
                                    :item-label="
                                        (step) =>
                                            step.display_name ||
                                            step.routine_item?.name
                                    "
                                    variant="table"
                                >
                                    <template #row="{ item: step, index }">
                                        <td class="px-4 py-3 text-cd-ink-muted">
                                            {{ index + 1 }}
                                        </td>
                                        <td
                                            class="px-4 py-3 font-sans font-semibold text-cd-ink"
                                        >
                                            {{
                                                step.display_name ||
                                                step.routine_item?.name ||
                                                '—'
                                            }}
                                            <span
                                                v-if="
                                                    step.title &&
                                                    step.routine_item?.name
                                                "
                                                class="mt-0.5 block font-sans text-xs font-normal text-cd-ink-muted"
                                            >
                                                {{ step.routine_item.name }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-cd-ink-muted">
                                            {{ formatStepTarget(step) }}
                                            <span
                                                class="before:mx-1.5 before:content-['·']"
                                            >
                                                {{
                                                    step.routine_item
                                                        ? trackingTypeLabels[
                                                              step.routine_item
                                                                  .tracking_type
                                                          ]
                                                        : ''
                                                }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-cd-ink-muted">
                                            {{
                                                formatDurationSeconds(
                                                    estimateStepDurationSeconds(
                                                        {
                                                            target_blocks:
                                                                step.target_blocks,
                                                            target_amount:
                                                                step.target_amount,
                                                            amount_unit:
                                                                step.amount_unit,
                                                            rest_seconds:
                                                                step.rest_seconds,
                                                            tracking_type:
                                                                step
                                                                    .routine_item
                                                                    ?.tracking_type,
                                                        },
                                                    ),
                                                )
                                            }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                class="inline-flex rounded-full border px-2 py-0.5 text-xs"
                                                :class="
                                                    purposeChipClasses(
                                                        stepPurposeKey(step),
                                                    )
                                                "
                                            >
                                                {{ stepPurpose(step) }}
                                            </span>
                                        </td>
                                        <td
                                            class="max-w-[160px] truncate px-4 py-3 text-cd-ink-muted"
                                        >
                                            {{ step.note ?? '—' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                v-if="step.video"
                                                class="inline-flex items-center gap-1 font-sans text-xs text-cd-ink-muted"
                                            >
                                                <Clapperboard
                                                    :size="14"
                                                    :stroke-width="1.6"
                                                />
                                                {{ step.video.title }}
                                            </span>
                                            <span
                                                v-else
                                                class="text-cd-ink-muted"
                                            >
                                                —
                                            </span>
                                        </td>
                                    </template>
                                    <template #actions="{ item: step }">
                                        <div class="flex items-center gap-1">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon-sm"
                                                aria-label="ステップを編集"
                                                @click="openEditStep(step)"
                                            >
                                                <Pencil
                                                    :size="14"
                                                    :stroke-width="1.6"
                                                />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon-sm"
                                                aria-label="ステップを削除"
                                                @click="deleteStep(step)"
                                            >
                                                <Trash2
                                                    :size="14"
                                                    :stroke-width="1.6"
                                                />
                                            </Button>
                                        </div>
                                    </template>
                                </ReorderableList>
                            </table>
                        </div>

                        <div
                            v-else
                            class="px-5 py-12 text-center font-sans text-sm text-cd-ink-muted"
                        >
                            <template v-if="isCreateMode">
                                <p>① の「名前を保存して次へ」が終わると、ここにステップを追加できます。</p>
                            </template>
                            <template v-else>
                                <p>ステップがまだありません。</p>
                                <p class="mt-2">
                                    「ステップを追加」→ 内容を入力 →
                                    「このステップを保存」の順です。
                                </p>
                            </template>
                        </div>
                    </section>

                    <section
                        v-if="flowPhase === 'ready'"
                        aria-label="今日/作戦"
                        class="cd-panel px-5 py-4"
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <h2
                                    class="font-sans text-base font-semibold text-cd-ink"
                                >
                                    <span
                                        class="mr-2 inline-flex size-6 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground"
                                    >
                                        3
                                    </span>
                                    今日/作戦
                                </h2>
                                <p class="mt-1 font-sans text-xs text-cd-ink-muted">
                                    このルーティンを今日の予定に登録して進みます（同じルーティンを複数回登録しても構いません）
                                </p>
                            </div>
                            <Button
                                type="button"
                                :disabled="applyingToToday"
                                @click="applyToToday"
                            >
                                <CalendarDays
                                    :size="16"
                                    :stroke-width="1.6"
                                />
                                {{
                                    applyingToToday
                                        ? '登録中…'
                                        : '今日/作戦に登録して進む'
                                }}
                            </Button>
                        </div>
                    </section>
                </div>

                <RoutineEditorSidebar
                    :routine="routine"
                    :other-routines="otherRoutines"
                    :flow-phase="flowPhase"
                    :applying-to-today="applyingToToday"
                    @apply-to-today="applyToToday"
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
