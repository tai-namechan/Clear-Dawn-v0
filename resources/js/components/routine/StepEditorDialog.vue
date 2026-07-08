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
import { apiFetch } from '@/lib/apiFetch';
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

interface Props {
    open: boolean;
    routineItems: RoutineItem[];
    saving?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    saving: false,
});

const emit = defineEmits<{
    'update:open': [value: boolean];
    submit: [payload: StepEditorPayload];
    'items-changed': [];
}>();

const mode = ref<'pick' | 'create'>('pick');
const selectedItemId = ref('');
const newItemName = ref('');
const newItemCategory = ref<RoutineItemCategory>('strength');
const newItemTrackingType = ref<TrackingType>('weight_reps');
const purpose = ref<StepPurpose | null>(null);
const trackingType = ref<TrackingType>('weight_reps');
const blockCount = ref(3);
const restSeconds = ref('60');
const note = ref('');
const videoId = ref<string | null>(null);
const blockRows = ref<BlockTargetRow[]>([]);
const videos = ref<Video[]>([]);
const creatingItem = ref(false);
const formError = ref<string | null>(null);

const selectedItem = computed(() =>
    props.routineItems.find((item) => item.id === selectedItemId.value) ?? null,
);

const effectiveTrackingType = computed<TrackingType>(() => {
    if (mode.value === 'create') {
        return newItemTrackingType.value;
    }

    return selectedItem.value?.tracking_type ?? trackingTypeModel.value;
});

const previewPurpose = computed(() =>
    resolveStepPurpose(
        purpose.value,
        mode.value === 'create'
            ? newItemCategory.value
            : (selectedItem.value?.category ?? null),
    ),
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

watch(
    () => props.open,
    async (isOpen) => {
        if (!isOpen) {
            return;
        }

        formError.value = null;
        videos.value = await fetchVideosFromPage();

        if (!selectedItemId.value && props.routineItems[0]) {
            selectedItemId.value = props.routineItems[0].id;
        }
    },
);

watch(selectedItem, (item) => {
    if (!item) {
        return;
    }

    trackingType.value = item.tracking_type;
    purpose.value = categoryDefaultPurpose[item.category] ?? null;
});

watch(newItemCategory, (category) => {
    purpose.value = categoryDefaultPurpose[category] ?? null;
});

const trackingTypeModel = computed<TrackingType>({
    get() {
        return mode.value === 'create'
            ? newItemTrackingType.value
            : trackingType.value;
    },
    set(value: TrackingType) {
        if (mode.value === 'create') {
            newItemTrackingType.value = value;
        } else {
            trackingType.value = value;
        }
    },
});

function close(): void {
    emit('update:open', false);
}

function resetCreateForm(): void {
    newItemName.value = '';
    newItemCategory.value = 'strength';
    newItemTrackingType.value = 'weight_reps';
    purpose.value = categoryDefaultPurpose.strength;
    blockCount.value = 3;
    restSeconds.value = '60';
    note.value = '';
    videoId.value = null;
    blockRows.value = [{ load: '', amount: '', memo: '' }];
}

function resetPickForm(): void {
    selectedItemId.value = props.routineItems[0]?.id ?? '';
    blockCount.value = 3;
    restSeconds.value = '60';
    note.value = '';
    videoId.value = null;
    blockRows.value = [{ load: '', amount: '', memo: '' }];
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            mode.value = props.routineItems.length ? 'pick' : 'create';
            resetPickForm();
            resetCreateForm();
        }
    },
);

async function createInlineItem(): Promise<RoutineItem | null> {
    if (!newItemName.value.trim()) {
        formError.value = '実施項目名を入力してください。';

        return null;
    }

    creatingItem.value = true;
    formError.value = null;

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
                        defaultAmountUnitForTracking[newItemTrackingType.value] ||
                        null,
                }),
            },
        );

        emit('items-changed');
        selectedItemId.value = result.routine_item.id;
        mode.value = 'pick';

        return result.routine_item;
    } catch {
        formError.value = '実施項目の作成に失敗しました。';

        return null;
    } finally {
        creatingItem.value = false;
    }
}

async function submit(): Promise<void> {
    formError.value = null;

    let routineItemId = selectedItemId.value;

    if (mode.value === 'create') {
        const created = await createInlineItem();

        if (!created) {
            return;
        }

        routineItemId = created.id;
    }

    if (!routineItemId) {
        formError.value = '実施項目を選択してください。';

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
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent
            class="flex max-h-[90vh] flex-col overflow-hidden bg-cd-surface sm:max-w-5xl"
        >
            <DialogHeader>
                <DialogTitle
                    class="font-serif text-lg tracking-[0.12em] text-cd-ink"
                >
                    ステップを追加
                </DialogTitle>
            </DialogHeader>

            <div class="grid min-h-0 flex-1 gap-6 overflow-y-auto lg:grid-cols-[minmax(0,1fr)_240px]">
                <div class="flex flex-col gap-5">
                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            size="sm"
                            :variant="mode === 'pick' ? 'default' : 'outline'"
                            @click="mode = 'pick'"
                        >
                            既存から選ぶ
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            :variant="mode === 'create' ? 'default' : 'outline'"
                            @click="mode = 'create'"
                        >
                            新規作成
                        </Button>
                    </div>

                    <div v-if="mode === 'pick'" class="space-y-2">
                        <Label class="font-sans text-xs text-cd-ink-muted">
                            実施項目
                        </Label>
                        <Select
                            v-model="selectedItemId"
                            :disabled="saving || !routineItems.length"
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="実施項目を選択" />
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
                    </div>

                    <div v-else class="space-y-4">
                        <div class="space-y-2">
                            <Label class="font-sans text-xs text-cd-ink-muted">
                                ステップ名
                            </Label>
                            <Input
                                v-model="newItemName"
                                placeholder="例: インテグレイテッド・ローテーション"
                                maxlength="100"
                                :disabled="saving || creatingItem"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label class="font-sans text-xs text-cd-ink-muted">
                                カテゴリー
                            </Label>
                            <ChipPicker
                                v-model="newItemCategory"
                                :options="categoryOptions"
                                :disabled="saving || creatingItem"
                                size="sm"
                            />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label class="font-sans text-xs text-cd-ink-muted">
                            目的
                        </Label>
                        <ChipPicker
                            v-model="purpose"
                            :options="purposeOptions"
                            :disabled="saving"
                            size="sm"
                        />
                    </div>

                    <div class="space-y-2">
                        <Label class="font-sans text-xs text-cd-ink-muted">
                            記録形式
                        </Label>
                        <ChipPicker
                            v-model="trackingTypeModel"
                            :options="trackingTabOptions"
                            :disabled="saving || mode === 'pick'"
                            size="sm"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <div class="space-y-2">
                            <Label class="font-sans text-xs text-cd-ink-muted">
                                セット数
                            </Label>
                            <Input
                                v-model.number="blockCount"
                                type="number"
                                min="1"
                                max="99"
                                :disabled="saving"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label class="font-sans text-xs text-cd-ink-muted">
                                休憩（秒）
                            </Label>
                            <Input
                                v-model="restSeconds"
                                type="number"
                                min="0"
                                max="3600"
                                :disabled="saving"
                            />
                        </div>
                        <div class="space-y-2 sm:col-span-1">
                            <Label class="font-sans text-xs text-cd-ink-muted">
                                動画（任意）
                            </Label>
                            <Select
                                :model-value="videoId ?? 'none'"
                                :disabled="saving"
                                @update:model-value="
                                    (v) =>
                                        (videoId =
                                            v && v !== 'none' ? String(v) : null)
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

                    <div
                        v-if="effectiveTrackingType !== 'check'"
                        class="space-y-2"
                    >
                        <Label class="font-sans text-xs text-cd-ink-muted">
                            セット内容の入力
                        </Label>
                        <BlockTargetGrid
                            v-model:rows="blockRows"
                            :tracking-type="effectiveTrackingType"
                            :block-count="blockCount"
                            :load-unit="loadUnit"
                            :amount-unit="amountUnit"
                            :disabled="saving"
                        />
                    </div>

                    <div class="space-y-2">
                        <Label class="font-sans text-xs text-cd-ink-muted">
                            メモ
                        </Label>
                        <textarea
                            v-model="note"
                            rows="3"
                            placeholder="フォームのポイントや注意事項"
                            class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-[80px] w-full rounded-md border px-3 py-2 font-sans text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="saving"
                        />
                    </div>

                    <p
                        v-if="formError"
                        class="font-sans text-sm text-destructive"
                    >
                        {{ formError }}
                    </p>
                </div>

                <aside
                    class="rounded-2xl border border-cd-line/60 bg-white/40 p-4"
                >
                    <p
                        class="font-sans text-xs tracking-[0.08em] text-cd-ink-muted"
                    >
                        プレビュー
                    </p>
                    <p
                        class="mt-3 font-serif text-base tracking-[0.08em] text-cd-ink"
                    >
                        {{
                            mode === 'create'
                                ? newItemName || '新しいステップ'
                                : selectedItem?.name || '—'
                        }}
                    </p>
                    <span
                        class="mt-2 inline-flex rounded-full border px-2 py-0.5 font-sans text-xs"
                        :class="purposeChipClasses(previewPurpose)"
                    >
                        {{ stepPurposeLabels[previewPurpose] }}
                    </span>
                    <ul class="mt-4 space-y-2 font-sans text-sm text-cd-ink">
                        <li>{{ previewTarget }}</li>
                        <li v-if="blockCount">
                            {{ blockCount }} セット
                        </li>
                        <li v-if="restSeconds">
                            休憩 {{ restSeconds }} 秒
                        </li>
                        <li
                            v-for="(row, index) in blockRows"
                            :key="index"
                            class="text-cd-ink-muted"
                        >
                            {{ index + 1 }}セット目:
                            <span v-if="row.load">{{ row.load }}{{ loadUnit }}</span>
                            <span v-if="row.load && row.amount"> × </span>
                            <span v-if="row.amount">{{ row.amount }}{{ amountUnit }}</span>
                            <span v-if="!row.load && !row.amount">—</span>
                        </li>
                    </ul>
                </aside>
            </div>

            <DialogFooter class="shrink-0">
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
                    保存して閉じる
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
