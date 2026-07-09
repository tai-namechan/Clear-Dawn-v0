<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import BlockTargetGrid, {
    type BlockTargetRow,
} from '@/components/routine/BlockTargetGrid.vue';
import ChipPicker from '@/components/routine/ChipPicker.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import InputError from '@/components/InputError.vue';
import { apiFetch, ApiError } from '@/lib/apiFetch';
import { fetchVideosFromPage } from '@/lib/fetchVideos';
import {
    categoryDefaultPurpose,
    defaultAmountUnitForTracking,
    defaultLoadUnit,
    formatStepTarget,
    resolveStepPurpose,
    routineItemCategoryLabels,
    routineItemCategoryOptions,
    stepPurposeLabels,
    stepPurposeOptions,
    trackingTypeOptions,
    trackingTypeTabLabels,
} from '@/lib/routineConstants';
import { purposeChipClasses } from '@/lib/stepPurposeColors';
import type {
    RoutineItem,
    RoutineItemCategory,
    StepPurpose,
    TrackingType,
    Video,
} from '@/types/routine';

export type StepEditorPayload = {
    routine_item_id: string;
    video_id?: string | null;
    purpose?: StepPurpose | null;
    target_blocks: number | null;
    target_load?: number | null;
    load_unit?: string | null;
    target_amount: number | null;
    amount_unit?: string | null;
    rest_seconds: number | null;
    note?: string | null;
};

type FieldErrors = {
    name?: string;
    purpose?: string;
    target_blocks?: string;
    rest_seconds?: string;
    target_amount?: string;
    general?: string;
};

interface Props {
    open: boolean;
    routineItems: RoutineItem[];
    videos?: Video[];
    saving?: boolean;
    /** Parent can push server-side field errors after submit fails */
    serverErrors?: FieldErrors | null;
}

const props = withDefaults(defineProps<Props>(), {
    saving: false,
    videos: () => [],
    serverErrors: null,
});

const emit = defineEmits<{
    'update:open': [value: boolean];
    submit: [payload: StepEditorPayload];
    'items-changed': [];
}>();

const mode = ref<'pick' | 'create'>('create');
const selectedItemId = ref('');
const newItemName = ref('');
const newItemCategory = ref<RoutineItemCategory>('strength');
const newItemTrackingType = ref<TrackingType>('reps');
const purpose = ref<StepPurpose | null>('strength');
const blockCount = ref(3);
const restSeconds = ref('60');
const note = ref('');
const videoId = ref<string | null>(null);
const blockRows = ref<BlockTargetRow[]>([
    { load: '', amount: '10', memo: '' },
]);
const videos = ref<Video[]>([...props.videos]);
const creatingItem = ref(false);
const fieldErrors = ref<FieldErrors>({});
const nameSectionRef = ref<HTMLElement | null>(null);

watch(
    () => props.serverErrors,
    (errors) => {
        if (errors) {
            fieldErrors.value = { ...errors };
            if (errors.name) {
                scrollToNameField();
            }
        }
    },
);

function clearFieldErrors(): void {
    fieldErrors.value = {};
}

function scrollToNameField(): void {
    requestAnimationFrame(() => {
        nameSectionRef.value?.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
        });
        const input = nameSectionRef.value?.querySelector('input');
        input?.focus();
    });
}

function applyApiErrors(error: unknown): void {
    if (!(error instanceof ApiError)) {
        fieldErrors.value = {
            general: '保存に失敗しました。もう一度お試しください。',
        };

        return;
    }

    const body = error.body as {
        message?: string;
        errors?: Record<string, string[]>;
    };
    const next: FieldErrors = {};

    if (body.errors) {
        if (body.errors.name?.[0]) {
            next.name = body.errors.name[0];
        }
        if (body.errors.routine_item_id?.[0]) {
            next.name = body.errors.routine_item_id[0];
        }
        if (body.errors.purpose?.[0]) {
            next.purpose = body.errors.purpose[0];
        }
        if (body.errors.target_blocks?.[0]) {
            next.target_blocks = body.errors.target_blocks[0];
        }
        if (body.errors.rest_seconds?.[0]) {
            next.rest_seconds = body.errors.rest_seconds[0];
        }
        if (body.errors.target_amount?.[0]) {
            next.target_amount = body.errors.target_amount[0];
        }
        if (body.errors.target_load?.[0]) {
            next.target_amount = body.errors.target_load[0];
        }
    }

    if (!Object.keys(next).length) {
        next.general =
            body.message ?? '保存に失敗しました。もう一度お試しください。';
    }

    fieldErrors.value = next;

    if (next.name) {
        scrollToNameField();
    }
}

const selectedItem = computed(
    () =>
        props.routineItems.find((item) => item.id === selectedItemId.value) ??
        null,
);

const trackingTypeModel = computed<TrackingType>({
    get() {
        return mode.value === 'create'
            ? newItemTrackingType.value
            : (selectedItem.value?.tracking_type ?? 'reps');
    },
    set(value: TrackingType) {
        if (mode.value === 'create') {
            newItemTrackingType.value = value;
        }
    },
});

const effectiveTrackingType = computed<TrackingType>(
    () => trackingTypeModel.value,
);

const previewPurpose = computed(() =>
    resolveStepPurpose(
        purpose.value,
        mode.value === 'create'
            ? newItemCategory.value
            : (selectedItem.value?.category ?? null),
    ),
);

const loadUnit = computed(() => {
    if (mode.value === 'pick' && selectedItem.value?.default_load_unit) {
        return selectedItem.value.default_load_unit;
    }

    return defaultLoadUnit;
});

const amountUnit = computed(() => {
    if (mode.value === 'pick' && selectedItem.value?.default_amount_unit) {
        return selectedItem.value.default_amount_unit;
    }

    return defaultAmountUnitForTracking[effectiveTrackingType.value];
});

const previewTarget = computed(() =>
    formatStepTarget({
        target_blocks: blockCount.value,
        target_load: blockRows.value[0]?.load || null,
        load_unit: loadUnit.value,
        target_amount: blockRows.value[0]?.amount || null,
        amount_unit: amountUnit.value,
        routine_item: {
            category:
                mode.value === 'create'
                    ? newItemCategory.value
                    : selectedItem.value?.category,
        },
    }),
);

const categoryOptions = computed(() =>
    routineItemCategoryOptions.map((value) => ({
        value,
        label: routineItemCategoryLabels[value],
    })),
);

const purposeOptions = computed(() =>
    stepPurposeOptions.map((value) => ({
        value,
        label: stepPurposeLabels[value],
    })),
);

const trackingTabOptions = computed(() =>
    trackingTypeOptions.map((value) => ({
        value,
        label: trackingTypeTabLabels[value],
    })),
);

const readyVideos = computed(() =>
    videos.value.filter((video) => video.status === 'ready'),
);

const stepName = computed(() =>
    mode.value === 'create'
        ? newItemName.value.trim() || '新しいステップ'
        : selectedItem.value?.name || '—',
);

watch(
    () => props.open,
    async (isOpen) => {
        if (!isOpen) {
            return;
        }

        clearFieldErrors();
        mode.value = props.routineItems.length ? 'pick' : 'create';
        selectedItemId.value = props.routineItems[0]?.id ?? '';
        newItemName.value = '';
        newItemCategory.value = 'strength';
        newItemTrackingType.value = 'reps';
        purpose.value = categoryDefaultPurpose.strength;
        blockCount.value = 3;
        restSeconds.value = '60';
        note.value = '';
        videoId.value = null;
        blockRows.value = [{ load: '', amount: '10', memo: '' }];
        videos.value = props.videos.length
            ? [...props.videos]
            : await fetchVideosFromPage().catch(() => []);
    },
);

watch(selectedItem, (item) => {
    if (!item || mode.value !== 'pick') {
        return;
    }

    purpose.value = categoryDefaultPurpose[item.category] ?? null;
});

watch(purpose, () => {
    fieldErrors.value.purpose = undefined;
});

watch(newItemCategory, (category) => {
    purpose.value = categoryDefaultPurpose[category] ?? null;
});

function close(): void {
    emit('update:open', false);
}

async function createInlineItem(): Promise<RoutineItem | null> {
    if (!newItemName.value.trim()) {
        fieldErrors.value = { name: 'ステップ名を入力してください。' };
        scrollToNameField();

        return null;
    }

    creatingItem.value = true;
    clearFieldErrors();

    try {
        const result = await apiFetch<{ routine_item: RoutineItem }>(
            '/routine-items',
            {
                method: 'POST',
                body: JSON.stringify({
                    name: newItemName.value.trim(),
                    category: newItemCategory.value,
                    tracking_type: newItemTrackingType.value,
                    default_load_unit:
                        newItemTrackingType.value === 'weight_reps'
                            ? defaultLoadUnit
                            : null,
                    default_amount_unit:
                        defaultAmountUnitForTracking[
                            newItemTrackingType.value
                        ] || null,
                }),
            },
        );

        emit('items-changed');
        selectedItemId.value = result.routine_item.id;
        mode.value = 'pick';

        return result.routine_item;
    } catch (error) {
        applyApiErrors(error);

        if (!fieldErrors.value.name) {
            fieldErrors.value = {
                ...fieldErrors.value,
                name:
                    fieldErrors.value.general ??
                    'ステップ名の作成に失敗しました。',
            };
            scrollToNameField();
        }

        return null;
    } finally {
        creatingItem.value = false;
    }
}

function validateClient(): boolean {
    const next: FieldErrors = {};

    if (mode.value === 'create' && !newItemName.value.trim()) {
        next.name = 'ステップ名を入力してください。';
    }

    if (mode.value === 'pick' && !selectedItemId.value) {
        next.name = 'ステップを選択してください。';
    }

    if (!purpose.value) {
        next.purpose = '目的を選択してください。';
    }

    if (!blockCount.value || blockCount.value < 1) {
        next.target_blocks = 'セット数は1以上で入力してください。';
    }

    fieldErrors.value = next;

    if (next.name) {
        scrollToNameField();
    }

    return Object.keys(next).length === 0;
}

async function submit(): Promise<void> {
    clearFieldErrors();

    if (!validateClient()) {
        return;
    }

    let routineItemId = selectedItemId.value;

    if (mode.value === 'create') {
        const created = await createInlineItem();

        if (!created) {
            return;
        }

        routineItemId = created.id;
    }

    if (!routineItemId) {
        fieldErrors.value = { name: 'ステップを選択してください。' };
        scrollToNameField();

        return;
    }

    const firstRow = blockRows.value[0] ?? {
        load: '',
        amount: '',
        memo: '',
    };
    const combinedNote = [note.value.trim(), firstRow.memo.trim()]
        .filter(Boolean)
        .join('\n');

    emit('submit', {
        routine_item_id: routineItemId,
        video_id: videoId.value,
        purpose: purpose.value,
        target_blocks: blockCount.value || null,
        target_load: firstRow.load ? Number(firstRow.load) : null,
        load_unit: loadUnit.value || null,
        target_amount: firstRow.amount ? Number(firstRow.amount) : null,
        amount_unit: amountUnit.value || null,
        rest_seconds: restSeconds.value ? Number(restSeconds.value) : null,
        note: combinedNote || null,
    });
}

defineExpose({
    applyApiErrors,
    clearFieldErrors,
});
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent
            class="flex max-h-[92vh] flex-col overflow-hidden bg-[#fffcf8] sm:max-w-5xl"
        >
            <DialogHeader>
                <DialogTitle class="font-sans text-lg font-semibold text-cd-ink">
                    ステップを追加
                </DialogTitle>
                <p class="font-sans text-sm text-cd-ink-muted">
                    上から順番に入力してください。右側で完成イメージを確認できます。
                </p>
            </DialogHeader>

            <div
                class="grid min-h-0 flex-1 gap-5 overflow-y-auto lg:grid-cols-[minmax(0,1fr)_240px]"
            >
                <div class="flex flex-col gap-3">
                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            size="sm"
                            :variant="mode === 'create' ? 'default' : 'outline'"
                            @click="mode = 'create'"
                        >
                            新しく作る
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :variant="mode === 'pick' ? 'default' : 'outline'"
                            :disabled="!routineItems.length"
                            @click="mode = 'pick'"
                        >
                            既存から選ぶ
                        </Button>
                    </div>

                    <section ref="nameSectionRef" class="cd-step-section">
                        <div class="cd-step-section__label">
                            <span class="cd-step-section__num">1</span>
                            ステップ名
                        </div>
                        <Input
                            v-if="mode === 'create'"
                            v-model="newItemName"
                            placeholder="例: スクワット / スケール練習"
                            maxlength="100"
                            :disabled="saving || creatingItem"
                            :aria-invalid="Boolean(fieldErrors.name)"
                            @input="fieldErrors.name = undefined"
                        />
                        <Select
                            v-else
                            v-model="selectedItemId"
                            :disabled="saving || !routineItems.length"
                        >
                            <SelectTrigger
                                :aria-invalid="Boolean(fieldErrors.name)"
                            >
                                <SelectValue placeholder="既存のステップを選択" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="item in routineItems"
                                    :key="item.id"
                                    :value="item.id"
                                >
                                    {{ item.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="fieldErrors.name" />
                    </section>

                    <section class="cd-step-section">
                        <div class="cd-step-section__label">
                            <span class="cd-step-section__num">2</span>
                            カテゴリ
                        </div>
                        <ChipPicker
                            v-if="mode === 'create'"
                            v-model="newItemCategory"
                            :options="categoryOptions"
                            :disabled="saving || creatingItem"
                            size="sm"
                        />
                        <p v-else class="font-sans text-sm text-cd-ink">
                            {{
                                selectedItem
                                    ? routineItemCategoryLabels[
                                          selectedItem.category
                                      ]
                                    : '—'
                            }}
                        </p>
                    </section>

                    <section class="cd-step-section">
                        <div class="cd-step-section__label">
                            <span class="cd-step-section__num">3</span>
                            目的
                        </div>
                        <ChipPicker
                            v-model="purpose"
                            :options="purposeOptions"
                            :disabled="saving"
                            size="sm"
                        />
                        <InputError :message="fieldErrors.purpose" />
                    </section>

                    <section class="cd-step-section">
                        <div class="cd-step-section__label">
                            <span class="cd-step-section__num">4</span>
                            記録方法（単位）
                        </div>
                        <ChipPicker
                            v-model="trackingTypeModel"
                            :options="trackingTabOptions"
                            :disabled="saving || mode === 'pick'"
                            size="sm"
                        />
                        <p class="font-sans text-xs text-cd-ink-muted">
                            実行時に入力する単位です。既存項目を選んだ場合は変更できません。
                        </p>
                    </section>

                    <section class="cd-step-section">
                        <div class="cd-step-section__label">
                            <span class="cd-step-section__num">5</span>
                            セット内容
                        </div>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <div class="space-y-1.5">
                                <Label class="text-xs text-cd-ink-muted">
                                    セット数
                                </Label>
                                <Input
                                    v-model.number="blockCount"
                                    type="number"
                                    min="1"
                                    max="99"
                                    :disabled="saving"
                                    :aria-invalid="
                                        Boolean(fieldErrors.target_blocks)
                                    "
                                    @input="
                                        fieldErrors.target_blocks = undefined
                                    "
                                />
                                <InputError
                                    :message="fieldErrors.target_blocks"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-xs text-cd-ink-muted">
                                    休憩（秒）
                                </Label>
                                <Input
                                    v-model="restSeconds"
                                    type="number"
                                    min="0"
                                    max="3600"
                                    :disabled="saving"
                                    :aria-invalid="
                                        Boolean(fieldErrors.rest_seconds)
                                    "
                                    @input="
                                        fieldErrors.rest_seconds = undefined
                                    "
                                />
                                <InputError
                                    :message="fieldErrors.rest_seconds"
                                />
                            </div>
                            <div class="space-y-1.5 sm:col-span-1">
                                <Label class="text-xs text-cd-ink-muted">
                                    動画（任意）
                                </Label>
                                <Select
                                    :model-value="videoId ?? 'none'"
                                    :disabled="saving"
                                    @update:model-value="
                                        (v) =>
                                            (videoId =
                                                v && v !== 'none'
                                                    ? String(v)
                                                    : null)
                                    "
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="なし" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            なし
                                        </SelectItem>
                                        <SelectItem
                                            v-for="video in readyVideos"
                                            :key="video.id"
                                            :value="video.id"
                                        >
                                            {{ video.title }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <BlockTargetGrid
                            v-if="effectiveTrackingType !== 'check'"
                            v-model:rows="blockRows"
                            :tracking-type="effectiveTrackingType"
                            :block-count="blockCount"
                            :load-unit="loadUnit"
                            :amount-unit="amountUnit"
                            :disabled="saving"
                        />
                        <InputError
                            v-if="fieldErrors.target_amount"
                            :message="fieldErrors.target_amount"
                        />
                    </section>

                    <section class="cd-step-section">
                        <div class="cd-step-section__label">
                            <span class="cd-step-section__num">6</span>
                            メモ（任意）
                        </div>
                        <textarea
                            v-model="note"
                            rows="3"
                            placeholder="フォームのポイントや注意事項"
                            class="border-input bg-white ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-[80px] w-full rounded-md border px-3 py-2 font-sans text-sm text-cd-ink focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="saving"
                        />
                    </section>

                    <p
                        v-if="fieldErrors.general"
                        class="font-sans text-sm text-destructive"
                        role="alert"
                    >
                        {{ fieldErrors.general }}
                    </p>
                </div>

                <aside class="cd-panel-muted h-fit p-4 lg:sticky lg:top-0">
                    <p class="font-sans text-xs font-semibold tracking-wide text-cd-ink-muted">
                        プレビュー
                    </p>
                    <p class="mt-3 font-sans text-base font-semibold text-cd-ink">
                        {{ stepName }}
                    </p>
                    <span
                        class="mt-2 inline-flex rounded-full border px-2 py-0.5 font-sans text-xs font-medium"
                        :class="purposeChipClasses(previewPurpose)"
                    >
                        {{ stepPurposeLabels[previewPurpose] }}
                    </span>
                    <ul class="mt-4 space-y-2 font-sans text-sm text-cd-ink">
                        <li class="font-medium">{{ previewTarget }}</li>
                        <li v-if="restSeconds">休憩 {{ restSeconds }} 秒</li>
                        <li
                            v-for="(row, index) in blockRows"
                            :key="index"
                            class="text-cd-ink-muted"
                        >
                            {{ index + 1 }}セット:
                            <span v-if="row.load">
                                {{ row.load }}{{ loadUnit }}
                            </span>
                            <span v-if="row.load && row.amount"> × </span>
                            <span v-if="row.amount">
                                {{ row.amount }}{{ amountUnit }}
                            </span>
                            <span v-if="!row.load && !row.amount">—</span>
                        </li>
                    </ul>
                </aside>
            </div>

            <DialogFooter class="shrink-0 border-t border-cd-line pt-4">
                <Button
                    type="button"
                    variant="ghost"
                    :disabled="saving || creatingItem"
                    @click="close"
                >
                    キャンセル
                </Button>
                <Button
                    type="button"
                    :disabled="saving || creatingItem"
                    @click="submit"
                >
                    追加する
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
