<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    Clapperboard,
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
import StepEditorDialog, {
    type StepEditorPayload,
} from '@/components/routine/StepEditorDialog.vue';
import { Button } from '@/components/ui/button';
import { apiFetch } from '@/lib/apiFetch';
import { ensureArray } from '@/lib/array';
import { fetchRoutineItemsFromPage } from '@/lib/fetchRoutineItems';
import { purposeChipClasses } from '@/lib/stepPurposeColors';
import {
    estimateStepDurationSeconds,
    formatDurationSeconds,
    formatStepTarget,
    resolveStepPurpose,
    routineItemCategoryLabels,
    stepPurposeLabels,
    trackingTypeLabels,
} from '@/lib/routineConstants';
import type { LifeArea } from '@/types/matrix';
import type { Routine, RoutineEditor, RoutineItem, RoutineStep } from '@/types/routine';

interface Props {
    routine: RoutineEditor;
    lifeAreas: LifeArea[];
    otherRoutines: Routine[];
}

const props = defineProps<Props>();

const formName = ref(props.routine.name);
const formDescription = ref(props.routine.description ?? '');
const formLifeAreaId = ref<string | null>(props.routine.life_area_id);
const savingRoutine = ref(false);
const savingStep = ref(false);
const showAddStepModal = ref(false);
const routineItems = ref<RoutineItem[]>([]);

const steps = computed(() => ensureArray(props.routine.steps));

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

    return top ? routineItemCategoryLabels[top[0] as keyof typeof routineItemCategoryLabels] : '—';
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
    routineItems.value = await fetchRoutineItemsFromPage();
}

async function openAddStep(): Promise<void> {
    await loadRoutineItems();
    showAddStepModal.value = true;
}

async function saveRoutine(): Promise<void> {
    if (!formName.value.trim()) {
        return;
    }

    savingRoutine.value = true;

    try {
        await apiFetch(`/routines/${props.routine.id}`, {
            method: 'PATCH',
            body: JSON.stringify({
                name: formName.value.trim(),
                description: formDescription.value.trim() || null,
                life_area_id: formLifeAreaId.value,
            }),
        });

        router.reload({ only: ['routine'] });
    } finally {
        savingRoutine.value = false;
    }
}

async function addStep(payload: StepEditorPayload): Promise<void> {
    savingStep.value = true;

    try {
        await apiFetch(`/routines/${props.routine.id}/steps`, {
            method: 'POST',
            body: JSON.stringify(payload),
        });

        showAddStepModal.value = false;
        router.reload({ only: ['routine'] });
    } finally {
        savingStep.value = false;
    }
}

async function deleteStep(step: RoutineStep): Promise<void> {
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
    <Head :title="`${routine.name} · ルーティン編集`" />

    <div
        class="flex min-h-full flex-1 flex-col overflow-x-auto rounded-xl p-4 md:px-6 md:pb-28"
    >
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
                        title="ルーティン編集"
                        subtitle="基本情報を整えたら、下でステップを順番に追加します。"
                        align="left"
                    />

                    <RoutinesHubTabs />
                </div>
            </PageSectionCard>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_280px]">
                <div class="flex flex-col gap-6">
                    <section
                        aria-label="基本情報"
                        class="cd-panel px-5 py-5"
                    >
                        <h2 class="font-sans text-base font-semibold text-cd-ink">
                            基本情報
                        </h2>

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

                        <div
                            class="mt-4 flex flex-wrap items-center gap-2 border-t border-cd-line pt-4"
                        >
                            <Link
                                href="/today"
                                class="inline-flex items-center gap-1.5 rounded-full border border-cd-line px-3 py-1.5 font-sans text-xs font-medium text-cd-ink-muted transition-colors hover:border-primary/30 hover:text-primary"
                            >
                                <CalendarDays :size="14" :stroke-width="1.6" />
                                今日やるへ進む
                            </Link>
                        </div>
                    </section>

                    <section
                        aria-label="ステップ一覧"
                        class="cd-panel overflow-hidden"
                    >
                        <div
                            class="flex items-center justify-between gap-3 border-b border-cd-line px-5 py-4"
                        >
                            <h2 class="font-sans text-base font-semibold text-cd-ink">
                                ステップ一覧
                            </h2>
                            <Button type="button" size="sm" @click="openAddStep">
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
                                    :item-label="(step) => step.routine_item?.name"
                                    variant="table"
                                >
                                    <template #row="{ item: step, index }">
                                        <td class="px-4 py-3 text-cd-ink-muted">
                                            {{ index + 1 }}
                                        </td>
                                        <td
                                            class="px-4 py-3 font-sans font-semibold text-cd-ink"
                                        >
                                            {{ step.routine_item?.name ?? '—' }}
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
                                                                step.routine_item
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
                                    </template>
                                </ReorderableList>
                            </table>
                        </div>

                        <div
                            v-else
                            class="px-5 py-12 text-center font-sans text-sm text-cd-ink-muted"
                        >
                            <p>ステップがまだありません。</p>
                            <p class="mt-2">
                                「ステップを追加」から実施項目を組み込みましょう。
                            </p>
                        </div>
                    </section>
                </div>

                <RoutineEditorSidebar
                    :routine="routine"
                    :other-routines="otherRoutines"
                />
            </div>
        </div>

        <div
            class="sticky bottom-0 border-t border-cd-line bg-[#fffcf8]/95 px-4 py-4 backdrop-blur md:px-6"
        >
            <div
                class="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-end gap-2"
            >
                <Button type="button" variant="ghost" as-child>
                    <Link href="/routines">キャンセル</Link>
                </Button>
                <Button
                    type="button"
                    :disabled="savingRoutine || !formName.trim()"
                    @click="saveRoutine"
                >
                    保存
                </Button>
            </div>
        </div>
    </div>

    <StepEditorDialog
        v-model:open="showAddStepModal"
        :routine-items="routineItems"
        :saving="savingStep"
        @submit="addStep"
        @items-changed="loadRoutineItems"
    />
</template>
