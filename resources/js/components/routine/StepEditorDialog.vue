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
    amountUnitPresets,
    categoryDefaultPurpose,
    defaultAmountUnitForTracking,
    defaultLoadUnit,
    formatStepTarget,
    itemNamePlaceholders,
    loadUnitPresets,
    resolveStepDisplayName,
    resolveStepPurpose,
    routineItemCategoryLabels,
    routineItemCategoryOptions,
    stepPurposeLabels,
    stepPurposeOptions,
    stepTitlePlaceholders,
    trackingTypeOptions,
    trackingTypeTabLabels,
    UNIT_PRESET_OTHER,
    unitSelectValue,
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
    title?: string | null;
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
    title?: string;
    purpose?: string;
    target_blocks?: string;
    rest_seconds?: string;
    target_load?: string;
    target_amount?: string;
    amount_unit?: string;
    load_unit?: string;
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
const stepTitle = ref('');
const newItemCategory = ref<RoutineItemCategory>('strength');
const newItemTrackingType = ref<TrackingType>('reps');
const purpose = ref<StepPurpose | null>('strength');
const blockCount = ref(3);
const restSeconds = ref('60');
const note = ref('');
const videoId = ref<string | null>(null);
const amountUnitCustom = ref('');
const loadUnitCustom = ref('');
const amountUnitSelect = ref<string>(amountUnitPresets[0] ?? '回');
const loadUnitSelect = ref<string>(loadUnitPresets[0] ?? 'kg');
const blockRows = ref<BlockTargetRow[]>([
    { load: '', amount: '10', memo: '' },
]);
const videos = ref<Video[]>([...props.videos]);
const creatingItem = ref(false);
const fieldErrors = ref<FieldErrors>({});
const nameSectionRef = ref<HTMLElement | null>(null);
const itemNamePlaceholder = itemNamePlaceholders[0];
const stepTitlePlaceholder = stepTitlePlaceholders[0];

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
        if (body.errors.title?.[0]) {
            next.title = body.errors.title[0];
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
            next.target_load = body.errors.target_load[0];
        }
        if (body.errors.load_unit?.[0]) {
            next.load_unit = body.errors.load_unit[0];
        }
        if (body.errors.amount_unit?.[0]) {
            next.amount_unit = body.errors.amount_unit[0];
        }
        if (body.errors.video_id?.[0]) {
            next.general = body.errors.video_id[0];
        }
        if (body.errors.note?.[0]) {
            next.general = body.errors.note[0];
        }
    }

    if (!Object.keys(next).length) {
        // Laravel default exception message is often just "Server Error"
        const message = body.message?.trim();
        next.general =
            !message || message === 'Server Error'
                ? '保存に失敗しました。入力内容を確認してもう一度お試しください。'
                : message;
    }

    fieldErrors.value = next;

    if (next.name) {
        scrollToNameField();
    } else if (next.target_load || next.target_amount || next.target_blocks) {
        requestAnimationFrame(() => {
            document
                .querySelector('[data-step-sets-section]')
                ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
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
    if (loadUnitSelect.value === UNIT_PRESET_OTHER) {
        return loadUnitCustom.value.trim() || defaultLoadUnit;
    }

    return loadUnitSelect.value || defaultLoadUnit;
});

const amountUnit = computed(() => {
    if (amountUnitSelect.value === UNIT_PRESET_OTHER) {
        return (
            amountUnitCustom.value.trim() ||
            defaultAmountUnitForTracking[effectiveTrackingType.value]
        );
    }

    return (
        amountUnitSelect.value ||
        defaultAmountUnitForTracking[effectiveTrackingType.value]
    );
});

const showLoadUnitPicker = computed(
    () => effectiveTrackingType.value === 'weight_reps',
);

const showAmountUnitPicker = computed(
    () =>
        effectiveTrackingType.value === 'weight_reps' ||
        effectiveTrackingType.value === 'reps' ||
        effectiveTrackingType.value === 'count' ||
        effectiveTrackingType.value === 'duration' ||
        effectiveTrackingType.value === 'distance',
);

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

function syncUnitSelectorsFromItem(item: RoutineItem | null): void {
    const amountDefault =
        item?.default_amount_unit ||
        defaultAmountUnitForTracking[item?.tracking_type ?? 'reps'] ||
        '回';
    const loadDefault = item?.default_load_unit || defaultLoadUnit;

    amountUnitSelect.value = unitSelectValue(amountDefault, amountUnitPresets);
    amountUnitCustom.value =
        amountUnitSelect.value === UNIT_PRESET_OTHER ? amountDefault : '';

    loadUnitSelect.value = unitSelectValue(loadDefault, loadUnitPresets);
    loadUnitCustom.value =
        loadUnitSelect.value === UNIT_PRESET_OTHER ? loadDefault : '';
}

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

const itemNamePreview = computed(() =>
    mode.value === 'create'
        ? newItemName.value.trim() || '実施項目未入力'
        : selectedItem.value?.name || '—',
);

const stepName = computed(() =>
    resolveStepDisplayName(stepTitle.value, itemNamePreview.value),
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
        stepTitle.value = '';
        newItemCategory.value = 'strength';
        newItemTrackingType.value = 'reps';
        purpose.value = categoryDefaultPurpose.strength;
        blockCount.value = 3;
        restSeconds.value = '60';
        note.value = '';
        videoId.value = null;
        blockRows.value = [{ load: '', amount: '10', memo: '' }];
        syncUnitSelectorsFromItem(props.routineItems[0] ?? null);
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
    syncUnitSelectorsFromItem(item);

    if (!videoId.value && item.default_video_id) {
        videoId.value = item.default_video_id;
    }
});

watch(newItemTrackingType, (trackingType) => {
    if (mode.value !== 'create') {
        return;
    }

    const amountDefault = defaultAmountUnitForTracking[trackingType] || '回';
    amountUnitSelect.value = unitSelectValue(amountDefault, amountUnitPresets);
    amountUnitCustom.value = '';
    loadUnitSelect.value = unitSelectValue(defaultLoadUnit, loadUnitPresets);
    loadUnitCustom.value = '';
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
        fieldErrors.value = { name: '実施項目名を入力してください。' };
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
                    default_load_unit: showLoadUnitPicker.value
                        ? loadUnit.value
                        : null,
                    default_amount_unit: showAmountUnitPicker.value
                        ? amountUnit.value || null
                        : null,
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
                    '実施項目の作成に失敗しました。',
            };
            scrollToNameField();
        }

        return null;
    } finally {
        creatingItem.value = false;
    }
}

function asText(value: unknown): string {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value).trim();
}

function validateClient(): boolean {
    const next: FieldErrors = {};

    if (mode.value === 'create' && !asText(newItemName.value)) {
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

    const firstRow = blockRows.value[0] ?? { load: '', amount: '', memo: '' };
    const tracking = effectiveTrackingType.value;
    const loadText = asText(firstRow.load);
    const amountText = asText(firstRow.amount);

    if (tracking === 'weight_reps' && !loadText) {
        next.target_load = '重量を入力してください。';
    }

    if (
        (tracking === 'weight_reps' ||
            tracking === 'reps' ||
            tracking === 'count' ||
            tracking === 'duration' ||
            tracking === 'distance') &&
        !amountText
    ) {
        next.target_amount =
            tracking === 'duration'
                ? '時間を入力してください。'
                : tracking === 'distance'
                  ? '距離を入力してください。'
                  : '回数を入力してください。';
    }

    fieldErrors.value = next;

    if (next.name) {
        scrollToNameField();
    } else if (next.target_load || next.target_amount || next.target_blocks) {
        requestAnimationFrame(() => {
            document
                .querySelector('[data-step-sets-section]')
                ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
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
        fieldErrors.value = { name: '実施項目を選択してください。' };
        scrollToNameField();

        return;
    }

    if (
        showAmountUnitPicker.value &&
        amountUnitSelect.value === UNIT_PRESET_OTHER &&
        !amountUnitCustom.value.trim()
    ) {
        fieldErrors.value = {
            amount_unit: '単位（その他）を入力してください。',
        };

        return;
    }

    if (
        showLoadUnitPicker.value &&
        loadUnitSelect.value === UNIT_PRESET_OTHER &&
        !loadUnitCustom.value.trim()
    ) {
        fieldErrors.value = {
            load_unit: '負荷の単位（その他）を入力してください。',
        };

        return;
    }

    const firstRow = blockRows.value[0] ?? {
        load: '',
        amount: '',
        memo: '',
    };
    const loadText = asText(firstRow.load);
    const amountText = asText(firstRow.amount);
    const combinedNote = [asText(note.value), asText(firstRow.memo)]
        .filter(Boolean)
        .join('\n');
    const titleText = asText(stepTitle.value);

    emit('submit', {
        routine_item_id: routineItemId,
        title: titleText || null,
        video_id: videoId.value,
        purpose: purpose.value,
        target_blocks: blockCount.value || null,
        target_load: loadText ? Number(loadText) : null,
        load_unit: showLoadUnitPicker.value ? loadUnit.value || null : null,
        target_amount: amountText ? Number(amountText) : null,
        amount_unit: showAmountUnitPicker.value
            ? amountUnit.value || null
            : null,
        rest_seconds: asText(restSeconds.value)
            ? Number(asText(restSeconds.value))
            : null,
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
                            実施項目
                        </div>
                        <Input
                            v-if="mode === 'create'"
                            v-model="newItemName"
                            :placeholder="itemNamePlaceholder"
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
                                <SelectValue placeholder="既存の実施項目を選択" />
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
                        <p class="font-sans text-xs text-cd-ink-muted">
                            カタログ名です。例: WGS / カノン Aパート / AWS IAM章 /
                            スクワット
                        </p>
                        <InputError :message="fieldErrors.name" />
                    </section>

                    <section class="cd-step-section">
                        <div class="cd-step-section__label">
                            <span class="cd-step-section__num">1b</span>
                            ステップ名（任意）
                        </div>
                        <Input
                            v-model="stepTitle"
                            :placeholder="stepTitlePlaceholder"
                            maxlength="100"
                            :disabled="saving"
                            :aria-invalid="Boolean(fieldErrors.title)"
                            @input="fieldErrors.title = undefined"
                        />
                        <p class="font-sans text-xs text-cd-ink-muted">
                            このルーティン内だけの表示名。未入力なら実施項目名を表示します。
                        </p>
                        <InputError :message="fieldErrors.title" />
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
                            実行時に入力する単位です。既存項目を選んだ場合も、このステップ用に単位を変えられます。
                        </p>
                        <div
                            v-if="showAmountUnitPicker || showLoadUnitPicker"
                            class="mt-3 grid gap-3 sm:grid-cols-2"
                        >
                            <div v-if="showLoadUnitPicker" class="space-y-1.5">
                                <Label class="text-xs text-cd-ink-muted">
                                    負荷の単位
                                </Label>
                                <Select
                                    v-model="loadUnitSelect"
                                    :disabled="saving"
                                >
                                    <SelectTrigger
                                        :aria-invalid="
                                            Boolean(fieldErrors.load_unit)
                                        "
                                    >
                                        <SelectValue placeholder="単位を選択" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="unit in loadUnitPresets"
                                            :key="unit"
                                            :value="unit"
                                        >
                                            {{ unit }}
                                        </SelectItem>
                                        <SelectItem :value="UNIT_PRESET_OTHER">
                                            その他
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Input
                                    v-if="loadUnitSelect === UNIT_PRESET_OTHER"
                                    v-model="loadUnitCustom"
                                    placeholder="例: バンド"
                                    maxlength="20"
                                    :disabled="saving"
                                    @input="fieldErrors.load_unit = undefined"
                                />
                                <InputError :message="fieldErrors.load_unit" />
                            </div>
                            <div
                                v-if="showAmountUnitPicker"
                                class="space-y-1.5"
                            >
                                <Label class="text-xs text-cd-ink-muted">
                                    量の単位
                                </Label>
                                <Select
                                    v-model="amountUnitSelect"
                                    :disabled="saving"
                                >
                                    <SelectTrigger
                                        :aria-invalid="
                                            Boolean(fieldErrors.amount_unit)
                                        "
                                    >
                                        <SelectValue placeholder="単位を選択" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="unit in amountUnitPresets"
                                            :key="unit"
                                            :value="unit"
                                        >
                                            {{ unit }}
                                        </SelectItem>
                                        <SelectItem :value="UNIT_PRESET_OTHER">
                                            その他
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <Input
                                    v-if="
                                        amountUnitSelect === UNIT_PRESET_OTHER
                                    "
                                    v-model="amountUnitCustom"
                                    placeholder="例: 小節 / BPM"
                                    maxlength="20"
                                    :disabled="saving"
                                    @input="
                                        fieldErrors.amount_unit = undefined
                                    "
                                />
                                <InputError
                                    :message="fieldErrors.amount_unit"
                                />
                            </div>
                        </div>
                    </section>

                    <section
                        data-step-sets-section
                        class="cd-step-section"
                    >
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
                                            なし（実施項目の既定動画を使う）
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
                                <p class="font-sans text-xs text-cd-ink-muted">
                                    未選択なら実施項目の既定動画を使います。必要ならルーティン用に差し替えられます。
                                </p>
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
                            :load-error="fieldErrors.target_load"
                            :amount-error="fieldErrors.target_amount"
                            @update:rows="
                                () => {
                                    fieldErrors.target_load = undefined;
                                    fieldErrors.target_amount = undefined;
                                }
                            "
                        />
                        <div
                            v-if="
                                fieldErrors.target_load ||
                                fieldErrors.target_amount
                            "
                            class="space-y-1"
                        >
                            <InputError :message="fieldErrors.target_load" />
                            <InputError :message="fieldErrors.target_amount" />
                        </div>
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
                    <p
                        v-if="
                            stepTitle.trim() &&
                            itemNamePreview !== '実施項目未入力' &&
                            itemNamePreview !== '—'
                        "
                        class="mt-1 font-sans text-xs text-cd-ink-muted"
                    >
                        実施項目: {{ itemNamePreview }}
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
                    このステップを保存
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
