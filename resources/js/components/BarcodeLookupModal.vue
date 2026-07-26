<script setup lang="ts">
import { Camera, Loader2, ScanLine } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useBarcodeScan } from '@/composables/useBarcodeScan';
import { downscaleLabelImage } from '@/composables/useLabelImageCapture';
import { apiFetch, ApiError } from '@/lib/apiFetch';
import type { FoodItem, MealEntry, MealSection } from '@/types/routine';

type Step =
    | 'scan'
    | 'ocr_capture'
    | 'polling'
    | 'confirm'
    | 'hit'
    | 'not_found'
    | 'manual'
    | 'one_off';

interface LookupResult {
    name: string | null;
    brands: string | null;
    serving_label: string;
    per: string;
    kcal: number | null;
    protein_g: number | null;
    fat_g: number | null;
    carb_g: number | null;
}

interface Props {
    open: boolean;
    date: string;
    defaultMealType?: MealSection['meal_type'];
}

interface Emits {
    (e: 'update:open', value: boolean): void;
    (e: 'food-registered', food: FoodItem): void;
    (e: 'food-hit', food: FoodItem): void;
    (e: 'meal-added', payload: { food: FoodItem | null; entry: MealEntry }): void;
}

const props = withDefaults(defineProps<Props>(), {
    defaultMealType: 'breakfast',
});
const emit = defineEmits<Emits>();

type PollingKind = 'off' | 'ocr';

const step = ref<Step>('scan');
const manualBarcode = ref('');
const knownBarcode = ref('');
const pollingKind = ref<PollingKind>('off');
const lookupId = ref<string | null>(null);
const lookupResult = ref<LookupResult | null>(null);
const lookupSource = ref<string | null>(null);
const hitFood = ref<FoodItem | null>(null);
const saving = ref(false);
const errorMessage = ref<string | null>(null);
const ocrFile = ref<File | null>(null);
const ocrPreviewUrl = ref<string | null>(null);
const labelFileInput = ref<HTMLInputElement | null>(null);
let pollTimer: ReturnType<typeof setTimeout> | null = null;

const confirmForm = ref({
    name: '',
    serving_label: '',
    barcode: '',
    brand: '',
    nutrition_basis: 'serving' as 'serving' | '100g' | 'package',
    kcal: '',
    protein_g: '',
    fat_g: '',
    carb_g: '',
    meal_type: 'breakfast' as MealSection['meal_type'],
    quantity: '1',
    note: '',
});

const { isSupported, scanning, error: scanError, videoRef, start: startCamera, stop: stopCamera } =
    useBarcodeScan(onBarcodeDetected);

const canConfirm = computed(() => {
    const f = confirmForm.value;

    return (
        f.name.trim() !== '' &&
        f.serving_label.trim() !== '' &&
        f.kcal !== '' &&
        Number(f.kcal) >= 0 &&
        f.protein_g !== '' &&
        Number(f.protein_g) >= 0 &&
        f.fat_g !== '' &&
        Number(f.fat_g) >= 0 &&
        f.carb_g !== '' &&
        Number(f.carb_g) >= 0
    );
});

const canOneOff = computed(() => {
    const f = confirmForm.value;

    return (
        f.name.trim() !== '' &&
        f.kcal !== '' &&
        Number(f.kcal) >= 0 &&
        f.protein_g !== '' &&
        Number(f.protein_g) >= 0 &&
        f.fat_g !== '' &&
        Number(f.fat_g) >= 0 &&
        f.carb_g !== '' &&
        Number(f.carb_g) >= 0 &&
        Number(f.quantity) >= 0.1
    );
});

const previewTotals = computed(() => {
    const q = Number(confirmForm.value.quantity) || 0;
    const kcal = Number(confirmForm.value.kcal) || 0;
    const p = Number(confirmForm.value.protein_g) || 0;
    const f = Number(confirmForm.value.fat_g) || 0;
    const c = Number(confirmForm.value.carb_g) || 0;

    return {
        kcal: Math.round(kcal * q * 10) / 10,
        protein_g: Math.round(p * q * 10) / 10,
        fat_g: Math.round(f * q * 10) / 10,
        carb_g: Math.round(c * q * 10) / 10,
    };
});

const quantityHint = computed(() => {
    const label = confirmForm.value.serving_label.trim() || '1サービング';
    const q = Number(confirmForm.value.quantity) || 0;

    return `${label} × ${q}`;
});

watch(
    () => props.open,
    (open) => {
        if (open) {
            reset();
        } else {
            cleanup();
        }
    },
);

function reset(): void {
    cleanup();
    step.value = 'scan';
    manualBarcode.value = '';
    knownBarcode.value = '';
    pollingKind.value = 'off';
    lookupId.value = null;
    lookupResult.value = null;
    lookupSource.value = null;
    hitFood.value = null;
    saving.value = false;
    errorMessage.value = null;
    confirmForm.value = {
        name: '',
        serving_label: '',
        barcode: '',
        brand: '',
        nutrition_basis: 'serving',
        kcal: '',
        protein_g: '',
        fat_g: '',
        carb_g: '',
        meal_type: props.defaultMealType,
        quantity: '1',
        note: '',
    };
}

function cleanup(): void {
    clearPollTimer();
    stopCamera();
    clearOcrFile();
}

function clearPollTimer(): void {
    if (pollTimer !== null) {
        clearTimeout(pollTimer);
        pollTimer = null;
    }
}

function clearOcrFile(): void {
    if (ocrPreviewUrl.value) {
        URL.revokeObjectURL(ocrPreviewUrl.value);
    }
    ocrPreviewUrl.value = null;
    ocrFile.value = null;
}

function close(): void {
    emit('update:open', false);
}

function onBarcodeDetected(code: string): void {
    void submitBarcode(code);
}

async function submitManualBarcode(): Promise<void> {
    await submitBarcode(manualBarcode.value.trim());
}

async function submitBarcode(code: string): Promise<void> {
    if (saving.value || code === '') {
        return;
    }

    saving.value = true;
    errorMessage.value = null;
    stopCamera();

    try {
        const data = await apiFetch<{
            status: string;
            food?: FoodItem;
            lookup_id?: string;
        }>('/meals/barcode-lookup', {
            method: 'POST',
            body: JSON.stringify({ barcode: code }),
        });

        knownBarcode.value = code;

        if (data.status === 'hit' && data.food) {
            hitFood.value = data.food;
            confirmForm.value.meal_type = props.defaultMealType;
            confirmForm.value.quantity = '1';
            step.value = 'hit';

            return;
        }

        lookupId.value = data.lookup_id ?? null;
        pollingKind.value = 'off';
        step.value = 'polling';
        startPolling();
    } catch (e) {
        if (e instanceof ApiError && e.status === 422) {
            const body = e.body as { errors?: Record<string, string[]> };
            errorMessage.value = body.errors?.barcode?.[0] ?? 'バーコードを確認してください。';
        } else {
            errorMessage.value = '検索に失敗しました。';
        }
    } finally {
        saving.value = false;
    }
}

function startPolling(): void {
    clearPollTimer();
    pollTimer = setTimeout(() => void pollLookup(), 500);
}

async function pollLookup(): Promise<void> {
    if (!lookupId.value || step.value !== 'polling') {
        return;
    }

    try {
        const data = await apiFetch<{
            status: string;
            result?: LookupResult;
            source?: string;
            error_code?: string;
        }>(`/meals/barcode-lookup/${lookupId.value}`);

        if (data.status === 'found' && data.result) {
            lookupResult.value = data.result;
            lookupSource.value = data.source ?? null;
            prefillConfirmForm(data.result);
            step.value = 'confirm';

            return;
        }

        if (data.status === 'not_found') {
            errorMessage.value = null;
            step.value = 'not_found';

            return;
        }

        if (data.status === 'failed') {
            if (data.error_code === 'ocr_unreadable') {
                errorMessage.value =
                    '成分表を読み取れませんでした。明るい場所で成分表全体が写るように撮り直してください。';
                step.value = 'ocr_capture';
            } else if (data.error_code === 'ocr_quota_exceeded') {
                errorMessage.value =
                    '今月のAI利用枠を使い切ったため読み取れません。手入力で登録してください。';
                step.value = 'not_found';
            } else if (data.error_code?.startsWith('ocr_')) {
                errorMessage.value =
                    '読み取りに失敗しました。もう一度撮影するか、手入力で登録してください。';
                step.value = 'ocr_capture';
            } else {
                errorMessage.value =
                    '照合に失敗しました。成分表撮影または手入力で続けられます。';
                step.value = 'not_found';
            }

            return;
        }

        pollTimer = setTimeout(() => void pollLookup(), 1000);
    } catch {
        errorMessage.value = '通信エラーが発生しました。';
        step.value = 'scan';
    }
}

function startOcrForMiss(): void {
    stopCamera();
    errorMessage.value = null;
    step.value = 'ocr_capture';
}

function startOcrWithoutBarcode(): void {
    stopCamera();
    lookupId.value = null;
    knownBarcode.value = '';
    errorMessage.value = null;
    step.value = 'ocr_capture';
}

function startManualEntry(): void {
    errorMessage.value = null;
    confirmForm.value = {
        ...confirmForm.value,
        name: '',
        serving_label: '1個',
        barcode: knownBarcode.value,
        brand: '',
        nutrition_basis: 'serving',
        kcal: '',
        protein_g: '',
        fat_g: '',
        carb_g: '',
        meal_type: props.defaultMealType,
        quantity: '1',
        note: '',
    };
    step.value = 'manual';
}

function startOneOffEntry(): void {
    errorMessage.value = null;
    confirmForm.value = {
        ...confirmForm.value,
        name: '',
        serving_label: '1食分',
        barcode: knownBarcode.value,
        brand: '',
        nutrition_basis: 'serving',
        kcal: '',
        protein_g: '',
        fat_g: '',
        carb_g: '',
        meal_type: props.defaultMealType,
        quantity: '1',
        note: '',
    };
    step.value = 'one_off';
}

function openLabelFilePicker(): void {
    labelFileInput.value?.click();
}

function onLabelFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    input.value = '';

    if (!file) {
        return;
    }

    clearOcrFile();
    ocrFile.value = file;
    ocrPreviewUrl.value = URL.createObjectURL(file);
}

async function submitLabelImage(): Promise<void> {
    if (!ocrFile.value || saving.value) {
        return;
    }

    saving.value = true;
    errorMessage.value = null;

    try {
        const image = await downscaleLabelImage(ocrFile.value);
        const form = new FormData();
        form.append('image', image);

        const url = lookupId.value
            ? `/meals/barcode-lookup/${lookupId.value}/label-image`
            : '/meals/label-ocr';

        const data = await apiFetch<{ status: string; lookup_id: string }>(url, {
            method: 'POST',
            body: form,
        });

        lookupId.value = data.lookup_id;
        clearOcrFile();
        pollingKind.value = 'ocr';
        step.value = 'polling';
        startPolling();
    } catch (e) {
        if (e instanceof ApiError && e.status === 422) {
            const body = e.body as {
                message?: string;
                errors?: Record<string, string[]>;
            };
            errorMessage.value =
                body.errors?.image?.[0] ?? body.message ?? '画像を確認してください。';
        } else if (e instanceof ApiError && e.status === 409) {
            pollingKind.value = 'ocr';
            step.value = 'polling';
            startPolling();
        } else {
            errorMessage.value = '送信に失敗しました。もう一度お試しください。';
        }
    } finally {
        saving.value = false;
    }
}

function prefillConfirmForm(result: LookupResult): void {
    const basis =
        result.per === '100g' ? '100g' : result.per === 'package' ? 'package' : 'serving';

    confirmForm.value = {
        name: result.name ?? '',
        serving_label: result.serving_label ?? (basis === '100g' ? '100g' : '1食分'),
        barcode: knownBarcode.value,
        brand: result.brands ?? '',
        nutrition_basis: basis,
        kcal: result.kcal != null ? String(result.kcal) : '',
        protein_g: result.protein_g != null ? String(result.protein_g) : '',
        fat_g: result.fat_g != null ? String(result.fat_g) : '',
        carb_g: result.carb_g != null ? String(result.carb_g) : '',
        meal_type: props.defaultMealType,
        quantity: '1',
        note: '',
    };
}

function nutritionPayload(): Record<string, unknown> {
    return {
        name: confirmForm.value.name.trim(),
        serving_label: confirmForm.value.serving_label.trim(),
        kcal: Number(confirmForm.value.kcal),
        protein_g: Number(confirmForm.value.protein_g),
        fat_g: Number(confirmForm.value.fat_g),
        carb_g: Number(confirmForm.value.carb_g),
        brand: confirmForm.value.brand.trim() || null,
        nutrition_basis: confirmForm.value.nutrition_basis,
        ...(confirmForm.value.barcode.trim() !== ''
            ? { barcode: confirmForm.value.barcode.trim() }
            : {}),
    };
}

async function confirmAndSave(addToMeal: boolean): Promise<void> {
    if (!lookupId.value || !canConfirm.value) {
        return;
    }

    saving.value = true;
    errorMessage.value = null;

    try {
        const body: Record<string, unknown> = {
            ...nutritionPayload(),
            add_to_meal: addToMeal,
        };

        if (addToMeal) {
            body.eaten_on = props.date;
            body.meal_type = confirmForm.value.meal_type;
            body.quantity = Number(confirmForm.value.quantity);
            body.note = confirmForm.value.note.trim() || null;
        }

        const data = await apiFetch<{ food: FoodItem; entry?: MealEntry }>(
            `/meals/barcode-lookup/${lookupId.value}/confirm`,
            {
                method: 'POST',
                body: JSON.stringify(body),
            },
        );

        if (addToMeal && data.entry) {
            emit('meal-added', { food: data.food, entry: data.entry });
        } else {
            emit('food-registered', data.food);
        }
        close();
    } catch (e) {
        if (e instanceof ApiError && e.status === 422) {
            const body = e.body as { errors?: Record<string, string[]> };
            const firstErr = Object.values(body.errors ?? {})[0];
            errorMessage.value = firstErr?.[0] ?? '入力内容を確認してください。';
        } else {
            errorMessage.value = '保存に失敗しました。';
        }
    } finally {
        saving.value = false;
    }
}

async function saveManual(addToMeal: boolean): Promise<void> {
    if (!canConfirm.value) {
        return;
    }

    saving.value = true;
    errorMessage.value = null;

    try {
        const body: Record<string, unknown> = {
            ...nutritionPayload(),
            save_mode: addToMeal ? 'food_and_meal' : 'food_only',
        };

        if (addToMeal) {
            body.eaten_on = props.date;
            body.meal_type = confirmForm.value.meal_type;
            body.quantity = Number(confirmForm.value.quantity);
            body.note = confirmForm.value.note.trim() || null;
        }

        const data = await apiFetch<{ food: FoodItem | null; entry: MealEntry | null }>(
            '/meals/foods/manual',
            {
                method: 'POST',
                body: JSON.stringify(body),
            },
        );

        if (addToMeal && data.entry) {
            emit('meal-added', { food: data.food, entry: data.entry });
        } else if (data.food) {
            emit('food-registered', data.food);
        }
        close();
    } catch (e) {
        if (e instanceof ApiError && e.status === 422) {
            const body = e.body as { errors?: Record<string, string[]> };
            const firstErr = Object.values(body.errors ?? {})[0];
            errorMessage.value = firstErr?.[0] ?? '入力内容を確認してください。';
        } else {
            errorMessage.value = '保存に失敗しました。';
        }
    } finally {
        saving.value = false;
    }
}

async function saveOneOff(): Promise<void> {
    if (!canOneOff.value) {
        return;
    }

    saving.value = true;
    errorMessage.value = null;

    try {
        const data = await apiFetch<{ food: null; entry: MealEntry }>(
            '/meals/foods/manual',
            {
                method: 'POST',
                body: JSON.stringify({
                    save_mode: 'one_off',
                    name: confirmForm.value.name.trim(),
                    kcal: Number(confirmForm.value.kcal),
                    protein_g: Number(confirmForm.value.protein_g),
                    fat_g: Number(confirmForm.value.fat_g),
                    carb_g: Number(confirmForm.value.carb_g),
                    eaten_on: props.date,
                    meal_type: confirmForm.value.meal_type,
                    quantity: Number(confirmForm.value.quantity),
                    note: confirmForm.value.note.trim() || null,
                }),
            },
        );

        emit('meal-added', { food: null, entry: data.entry });
        close();
    } catch (e) {
        if (e instanceof ApiError && e.status === 422) {
            const body = e.body as { errors?: Record<string, string[]> };
            const firstErr = Object.values(body.errors ?? {})[0];
            errorMessage.value = firstErr?.[0] ?? '入力内容を確認してください。';
        } else {
            errorMessage.value = '保存に失敗しました。';
        }
    } finally {
        saving.value = false;
    }
}

async function addHitFoodToMeal(): Promise<void> {
    if (!hitFood.value) {
        return;
    }

    saving.value = true;
    errorMessage.value = null;

    try {
        const data = await apiFetch<{ entry: MealEntry }>('/meals', {
            method: 'POST',
            body: JSON.stringify({
                eaten_on: props.date,
                meal_type: confirmForm.value.meal_type,
                food_item_id: hitFood.value.id,
                quantity: Number(confirmForm.value.quantity),
                note: confirmForm.value.note.trim() || null,
            }),
        });

        emit('meal-added', { food: hitFood.value, entry: data.entry });
        close();
    } catch {
        errorMessage.value = '食事への追加に失敗しました。';
    } finally {
        saving.value = false;
    }
}

function useHitFood(): void {
    if (hitFood.value) {
        emit('food-hit', hitFood.value);
        close();
    }
}

const dialogTitle = computed(() => {
    switch (step.value) {
        case 'scan':
            return 'バーコードスキャン';
        case 'ocr_capture':
            return '成分表を撮影';
        case 'polling':
            return pollingKind.value === 'ocr' ? '読み取り中...' : '照合中...';
        case 'confirm':
            return '栄養情報の確認';
        case 'hit':
            return '登録済み食品';
        case 'not_found':
            return '商品が見つかりませんでした';
        case 'manual':
            return '手入力で登録';
        case 'one_off':
            return '今回だけ直接入力';
        default:
            return 'バーコード';
    }
});
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent
            class="max-h-[90dvh] overflow-y-auto bg-cd-surface sm:max-w-lg"
        >
            <DialogHeader>
                <DialogTitle class="font-sans">
                    {{ dialogTitle }}
                </DialogTitle>
                <DialogDescription class="font-sans text-sm text-cd-ink-muted">
                    <template v-if="step === 'scan'">
                        バーコードを読み取るか、成分表を撮影して登録できます。
                    </template>
                    <template v-else-if="step === 'ocr_capture'">
                        栄養成分表示を撮影すると AI が読み取ります。
                    </template>
                    <template v-else-if="step === 'polling'">
                        {{
                            pollingKind === 'ocr'
                                ? '成分表を AI が読み取っています...'
                                : 'データベースを照合しています...'
                        }}
                    </template>
                    <template v-else-if="step === 'confirm'">
                        内容を確認し、保存して食事に追加できます。
                    </template>
                    <template v-else-if="step === 'hit'">
                        数量と食事区分を指定して当日の食事へ追加できます。
                    </template>
                    <template v-else-if="step === 'not_found'">
                        Open Food Facts に未登録です。次の方法で続けられます。
                    </template>
                    <template v-else-if="step === 'manual'">
                        読み取ったバーコードを引き継いでマイ食品へ登録します。
                    </template>
                    <template v-else-if="step === 'one_off'">
                        マイ食品には保存せず、今日の食事記録だけ作成します。
                    </template>
                </DialogDescription>
            </DialogHeader>

            <p
                v-if="errorMessage"
                class="rounded-lg bg-destructive/10 px-3 py-2 font-sans text-sm text-destructive"
            >
                {{ errorMessage }}
            </p>

            <div v-if="step === 'scan'" class="flex flex-col gap-4">
                <div v-if="isSupported" class="relative overflow-hidden rounded-xl bg-black">
                    <video
                        ref="videoRef"
                        class="aspect-video w-full object-cover"
                        muted
                        playsinline
                    />
                    <div
                        v-if="scanning"
                        class="pointer-events-none absolute inset-0 flex items-center justify-center"
                    >
                        <div class="h-0.5 w-3/4 animate-pulse rounded bg-primary/70" />
                    </div>
                    <div
                        v-if="!scanning"
                        class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-black/60"
                    >
                        <ScanLine :size="32" class="text-white/70" />
                        <Button type="button" size="sm" class="font-sans" @click="startCamera">
                            カメラを起動
                        </Button>
                    </div>
                </div>

                <p v-if="scanError" class="font-sans text-sm text-cd-ink-muted">
                    {{ scanError }}
                </p>

                <div class="flex flex-col gap-2">
                    <Label class="font-sans text-xs text-cd-ink-muted">
                        または番号を直接入力
                    </Label>
                    <div class="flex gap-2">
                        <Input
                            v-model="manualBarcode"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            maxlength="20"
                            placeholder="4901234567890"
                            @keydown.enter="submitManualBarcode"
                        />
                        <Button
                            type="button"
                            class="shrink-0 font-sans"
                            :disabled="saving || manualBarcode.trim() === ''"
                            @click="submitManualBarcode"
                        >
                            検索
                        </Button>
                    </div>
                </div>

                <div class="flex items-center gap-3" aria-hidden="true">
                    <div class="h-px flex-1 bg-cd-line" />
                    <span class="font-sans text-xs text-cd-ink-muted">または</span>
                    <div class="h-px flex-1 bg-cd-line" />
                </div>

                <button
                    type="button"
                    class="flex items-center gap-3 rounded-xl border border-cd-line px-4 py-3 text-left transition-colors hover:border-primary/40 hover:bg-primary/5"
                    @click="startOcrWithoutBarcode"
                >
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10"
                    >
                        <Camera
                            :size="20"
                            :stroke-width="1.6"
                            class="text-primary"
                        />
                    </div>
                    <div class="min-w-0">
                        <p class="font-sans text-sm font-semibold text-cd-ink">
                            成分表を撮影
                        </p>
                        <p class="mt-0.5 font-sans text-xs text-cd-ink-muted">
                            バーコードがない商品も、栄養成分表示から登録できます
                        </p>
                    </div>
                </button>
            </div>

            <div v-if="step === 'not_found'" class="flex flex-col gap-3">
                <p v-if="knownBarcode" class="font-sans text-xs text-cd-ink-muted">
                    バーコード: {{ knownBarcode }}
                </p>
                <Button type="button" class="font-sans justify-start" @click="startOcrForMiss">
                    <Camera :size="16" class="mr-2" />
                    成分表を撮影
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="font-sans justify-start"
                    @click="startManualEntry"
                >
                    手入力で登録
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="font-sans justify-start"
                    @click="startOneOffEntry"
                >
                    今回だけ直接入力
                </Button>
            </div>

            <div v-if="step === 'ocr_capture'" class="flex flex-col gap-4">
                <input
                    ref="labelFileInput"
                    type="file"
                    accept="image/*"
                    capture="environment"
                    class="hidden"
                    @change="onLabelFileSelected"
                />

                <div
                    v-if="ocrPreviewUrl"
                    class="overflow-hidden rounded-xl border border-cd-line"
                >
                    <img
                        :src="ocrPreviewUrl"
                        alt="成分表のプレビュー"
                        class="max-h-64 w-full object-contain"
                    />
                </div>
                <button
                    v-else
                    type="button"
                    class="flex aspect-video w-full flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-cd-line bg-muted/30 text-cd-ink-muted"
                    @click="openLabelFilePicker"
                >
                    <Camera :size="32" />
                    <span class="font-sans text-sm">栄養成分表示を撮影 / 選択</span>
                </button>

                <div class="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="flex-1 font-sans"
                        @click="openLabelFilePicker"
                    >
                        {{ ocrPreviewUrl ? '撮り直す' : '撮影 / 選択' }}
                    </Button>
                    <Button
                        type="button"
                        class="flex-1 font-sans"
                        :disabled="saving || !ocrFile"
                        @click="submitLabelImage"
                    >
                        AIで読み取る
                    </Button>
                </div>
            </div>

            <div v-if="step === 'polling'" class="flex flex-col items-center gap-4 py-8">
                <Loader2 :size="32" class="animate-spin text-primary" />
                <p class="font-sans text-sm text-cd-ink-muted">
                    {{
                        pollingKind === 'ocr'
                            ? '読み取っています…（数十秒かかることがあります）'
                            : '照合しています…'
                    }}
                </p>
            </div>

            <div
                v-if="step === 'confirm' || step === 'manual' || step === 'one_off'"
                class="flex flex-col gap-3"
            >
                <p
                    v-if="step === 'confirm' && lookupSource"
                    class="font-sans text-xs text-cd-ink-muted"
                >
                    出典:
                    {{
                        lookupSource === 'openfoodfacts'
                            ? 'Open Food Facts'
                            : lookupSource === 'label_ocr'
                              ? 'AI読み取り（成分表）· 値を必ず確認してください'
                              : lookupSource
                    }}
                    <template v-if="lookupResult?.brands">
                        · {{ lookupResult.brands }}
                    </template>
                    <template v-if="lookupResult?.per">
                        ·
                        {{
                            lookupResult.per === 'serving'
                                ? '1食分'
                                : lookupResult.per === 'package'
                                  ? '1包装あたり'
                                  : '100g あたり'
                        }}
                    </template>
                </p>

                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2 flex flex-col gap-1">
                        <Label class="font-sans text-xs">
                            商品名 <span class="text-destructive">*</span>
                        </Label>
                        <Input v-model="confirmForm.name" type="text" maxlength="100" />
                    </div>
                    <div
                        v-if="step !== 'one_off'"
                        class="col-span-2 flex flex-col gap-1"
                    >
                        <Label class="font-sans text-xs">
                            ブランド・メーカー（任意）
                        </Label>
                        <Input v-model="confirmForm.brand" type="text" maxlength="100" />
                    </div>
                    <div
                        v-if="step !== 'one_off'"
                        class="col-span-2 flex flex-col gap-1"
                    >
                        <Label class="font-sans text-xs">
                            1サービング <span class="text-destructive">*</span>
                        </Label>
                        <Input v-model="confirmForm.serving_label" type="text" maxlength="50" />
                    </div>
                    <div
                        v-if="step !== 'one_off'"
                        class="col-span-2 flex flex-col gap-1"
                    >
                        <Label class="font-sans text-xs">栄養基準</Label>
                        <select
                            v-model="confirmForm.nutrition_basis"
                            class="rounded-md border border-input bg-transparent px-3 py-2 font-sans text-sm"
                        >
                            <option value="serving">1サービング</option>
                            <option value="100g">100g あたり</option>
                            <option value="package">1包装あたり</option>
                        </select>
                    </div>
                    <div
                        v-if="step !== 'one_off'"
                        class="col-span-2 flex flex-col gap-1"
                    >
                        <Label class="font-sans text-xs">バーコード（任意）</Label>
                        <Input
                            v-model="confirmForm.barcode"
                            type="text"
                            inputmode="numeric"
                            maxlength="20"
                            placeholder="JAN / EAN を入力"
                            autocomplete="off"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label class="font-sans text-xs">
                            kcal <span class="text-destructive">*</span>
                        </Label>
                        <Input
                            v-model="confirmForm.kcal"
                            type="number"
                            min="0"
                            max="9999"
                            step="0.1"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label class="font-sans text-xs">
                            P (g) <span class="text-destructive">*</span>
                        </Label>
                        <Input
                            v-model="confirmForm.protein_g"
                            type="number"
                            min="0"
                            max="999"
                            step="0.1"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label class="font-sans text-xs">
                            F (g) <span class="text-destructive">*</span>
                        </Label>
                        <Input
                            v-model="confirmForm.fat_g"
                            type="number"
                            min="0"
                            max="999"
                            step="0.1"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label class="font-sans text-xs">
                            C (g) <span class="text-destructive">*</span>
                        </Label>
                        <Input
                            v-model="confirmForm.carb_g"
                            type="number"
                            min="0"
                            max="999"
                            step="0.1"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label class="font-sans text-xs">食事区分</Label>
                        <select
                            v-model="confirmForm.meal_type"
                            class="rounded-md border border-input bg-transparent px-3 py-2 font-sans text-sm"
                        >
                            <option value="breakfast">朝食</option>
                            <option value="lunch">昼食</option>
                            <option value="dinner">夕食</option>
                            <option value="snack">間食</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label class="font-sans text-xs">数量</Label>
                        <Input
                            v-model="confirmForm.quantity"
                            type="number"
                            min="0.1"
                            max="100"
                            step="0.1"
                        />
                        <p class="font-sans text-[11px] text-cd-ink-muted">
                            {{ quantityHint }}
                        </p>
                    </div>
                    <div class="col-span-2 rounded-lg bg-muted/40 px-3 py-2 font-sans text-xs text-cd-ink-muted">
                        記録予定:
                        {{ previewTotals.kcal }} kcal · P {{ previewTotals.protein_g }}g · F
                        {{ previewTotals.fat_g }}g · C {{ previewTotals.carb_g }}g
                    </div>
                    <div class="col-span-2 flex flex-col gap-1">
                        <Label class="font-sans text-xs">メモ（任意）</Label>
                        <Input v-model="confirmForm.note" type="text" maxlength="500" />
                    </div>
                </div>
            </div>

            <div v-if="step === 'hit' && hitFood" class="flex flex-col gap-3 py-2">
                <div class="rounded-xl border border-cd-line bg-muted/30 px-4 py-3">
                    <p class="font-sans text-sm font-semibold text-cd-ink">
                        {{ hitFood.name }}
                    </p>
                    <p class="mt-1 font-sans text-xs text-cd-ink-muted">
                        {{ hitFood.serving_label }} · {{ hitFood.kcal }} kcal
                    </p>
                    <p class="mt-1 font-sans text-xs">
                        <span class="text-cd-pfc-p">P {{ hitFood.protein_g }}g</span>
                        ·
                        <span class="text-cd-pfc-f">F {{ hitFood.fat_g }}g</span>
                        ·
                        <span class="text-cd-pfc-c">C {{ hitFood.carb_g }}g</span>
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="flex flex-col gap-1">
                        <Label class="font-sans text-xs">食事区分</Label>
                        <select
                            v-model="confirmForm.meal_type"
                            class="rounded-md border border-input bg-transparent px-3 py-2 font-sans text-sm"
                        >
                            <option value="breakfast">朝食</option>
                            <option value="lunch">昼食</option>
                            <option value="dinner">夕食</option>
                            <option value="snack">間食</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label class="font-sans text-xs">数量</Label>
                        <Input
                            v-model="confirmForm.quantity"
                            type="number"
                            min="0.1"
                            max="100"
                            step="0.1"
                        />
                        <p class="font-sans text-[11px] text-cd-ink-muted">
                            {{ hitFood.serving_label }} × {{ confirmForm.quantity }}
                        </p>
                    </div>
                </div>
            </div>

            <DialogFooter
                v-if="step === 'confirm' || step === 'manual'"
                class="flex-col gap-2 sm:flex-col"
            >
                <Button
                    type="button"
                    class="w-full font-sans"
                    :disabled="saving || !canConfirm"
                    @click="step === 'manual' ? saveManual(true) : confirmAndSave(true)"
                >
                    保存して食事に追加
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="w-full font-sans"
                    :disabled="saving || !canConfirm"
                    @click="step === 'manual' ? saveManual(false) : confirmAndSave(false)"
                >
                    マイ食品にだけ保存
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    class="w-full font-sans"
                    @click="step === 'manual' ? (step = 'not_found') : close()"
                >
                    {{ step === 'manual' ? '戻る' : 'キャンセル' }}
                </Button>
            </DialogFooter>

            <DialogFooter v-if="step === 'one_off'" class="flex-col gap-2 sm:flex-col">
                <Button
                    type="button"
                    class="w-full font-sans"
                    :disabled="saving || !canOneOff"
                    @click="saveOneOff"
                >
                    食事に追加（保存しない）
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    class="w-full font-sans"
                    @click="step = 'not_found'"
                >
                    戻る
                </Button>
            </DialogFooter>

            <DialogFooter v-if="step === 'hit'" class="flex-col gap-2 sm:flex-col">
                <Button
                    type="button"
                    class="w-full font-sans"
                    :disabled="saving"
                    @click="addHitFoodToMeal"
                >
                    食事に追加
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    class="w-full font-sans"
                    @click="useHitFood"
                >
                    詳細を編集して使う
                </Button>
                <Button type="button" variant="ghost" class="w-full font-sans" @click="close">
                    キャンセル
                </Button>
            </DialogFooter>

            <DialogFooter v-if="step === 'scan' || step === 'not_found'">
                <Button type="button" variant="outline" class="font-sans" @click="close">
                    閉じる
                </Button>
            </DialogFooter>

            <DialogFooter v-if="step === 'ocr_capture'">
                <Button
                    type="button"
                    variant="outline"
                    class="font-sans"
                    @click="step = lookupId ? 'not_found' : 'scan'"
                >
                    戻る
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
