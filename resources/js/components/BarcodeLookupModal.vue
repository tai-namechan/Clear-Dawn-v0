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
import type { FoodItem } from '@/types/routine';

type Step = 'scan' | 'ocr_capture' | 'polling' | 'confirm' | 'hit';

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
}

interface Emits {
    (e: 'update:open', value: boolean): void;
    (e: 'food-registered', food: FoodItem): void;
    (e: 'food-hit', food: FoodItem): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const step = ref<Step>('scan');
const manualBarcode = ref('');
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
    kcal: '',
    protein_g: '',
    fat_g: '',
    carb_g: '',
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
    step.value = 'scan';
    manualBarcode.value = '';
    lookupId.value = null;
    lookupResult.value = null;
    lookupSource.value = null;
    hitFood.value = null;
    saving.value = false;
    errorMessage.value = null;
    clearOcrFile();
    confirmForm.value = { name: '', serving_label: '', kcal: '', protein_g: '', fat_g: '', carb_g: '' };
}

function cleanup(): void {
    stopCamera();
    clearPollTimer();
    clearOcrFile();
}

function clearOcrFile(): void {
    if (ocrPreviewUrl.value !== null) {
        URL.revokeObjectURL(ocrPreviewUrl.value);
    }

    ocrFile.value = null;
    ocrPreviewUrl.value = null;
}

function clearPollTimer(): void {
    if (pollTimer !== null) {
        clearTimeout(pollTimer);
        pollTimer = null;
    }
}

function close(): void {
    emit('update:open', false);
}

async function onBarcodeDetected(barcode: string): Promise<void> {
    stopCamera();
    await submitBarcode(barcode);
}

async function submitManualBarcode(): Promise<void> {
    const code = manualBarcode.value.trim();

    if (code === '') {
        return;
    }

    await submitBarcode(code);
}

async function submitBarcode(barcode: string): Promise<void> {
    saving.value = true;
    errorMessage.value = null;

    try {
        const data = await apiFetch<
            | { status: 'hit'; food: FoodItem }
            | { status: 'pending'; lookup_id: string }
        >('/meals/barcode-lookup', {
            method: 'POST',
            body: JSON.stringify({ barcode }),
        });

        if (data.status === 'hit') {
            hitFood.value = data.food;
            step.value = 'hit';
        } else {
            lookupId.value = data.lookup_id;
            step.value = 'polling';
            startPolling();
        }
    } catch (e) {
        if (e instanceof ApiError && e.status === 422) {
            const body = e.body as { errors?: Record<string, string[]> };
            const msgs = body.errors?.barcode;
            errorMessage.value = msgs?.[0] ?? 'バーコードの形式が正しくありません。';
        } else {
            errorMessage.value = '送信に失敗しました。もう一度お試しください。';
        }
    } finally {
        saving.value = false;
    }
}

function startPolling(): void {
    clearPollTimer();
    pollTimer = setTimeout(() => void pollLookup(), 1500);
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
            errorMessage.value =
                'Open Food Facts に見つかりませんでした。「成分表を撮影して登録」で AI 読み取りができます。';
            step.value = 'scan';

            return;
        }

        if (data.status === 'failed') {
            if (data.error_code === 'ocr_unreadable') {
                errorMessage.value =
                    '成分表を読み取れませんでした。明るい場所で成分表全体が写るように撮り直してください。';
                step.value = 'ocr_capture';
            } else if (data.error_code === 'ocr_quota_exceeded') {
                errorMessage.value =
                    '今月のAI利用枠を使い切ったため読み取れません。直接入力で登録してください。';
                step.value = 'scan';
            } else if (data.error_code?.startsWith('ocr_')) {
                errorMessage.value =
                    '読み取りに失敗しました。もう一度撮影するか、直接入力で登録してください。';
                step.value = 'ocr_capture';
            } else {
                errorMessage.value =
                    '照合に失敗しました。もう一度スキャンするか、「成分表を撮影して登録」をお試しください。';
                step.value = 'scan';
            }

            return;
        }

        pollTimer = setTimeout(() => void pollLookup(), 2000);
    } catch {
        errorMessage.value = '通信エラーが発生しました。';
        step.value = 'scan';
    }
}

/** 入口1: F1 miss した lookup に成分表を添付して再解析 */
function startOcrForMiss(): void {
    stopCamera();
    errorMessage.value = null;
    step.value = 'ocr_capture';
}

/** 入口2: バーコードなしで成分表から直接登録 */
function startOcrWithoutBarcode(): void {
    stopCamera();
    lookupId.value = null;
    errorMessage.value = null;
    step.value = 'ocr_capture';
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
            // すでに解析中: そのままポーリングへ合流
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
    confirmForm.value = {
        name: result.name ?? '',
        serving_label: result.serving_label ?? '100g',
        kcal: result.kcal != null ? String(result.kcal) : '',
        protein_g: result.protein_g != null ? String(result.protein_g) : '',
        fat_g: result.fat_g != null ? String(result.fat_g) : '',
        carb_g: result.carb_g != null ? String(result.carb_g) : '',
    };
}

async function confirmAndSave(): Promise<void> {
    if (!lookupId.value || !canConfirm.value) {
        return;
    }

    saving.value = true;
    errorMessage.value = null;

    try {
        const data = await apiFetch<{ food: FoodItem }>(
            `/meals/barcode-lookup/${lookupId.value}/confirm`,
            {
                method: 'POST',
                body: JSON.stringify({
                    name: confirmForm.value.name.trim(),
                    serving_label: confirmForm.value.serving_label.trim(),
                    kcal: Number(confirmForm.value.kcal),
                    protein_g: Number(confirmForm.value.protein_g),
                    fat_g: Number(confirmForm.value.fat_g),
                    carb_g: Number(confirmForm.value.carb_g),
                }),
            },
        );

        emit('food-registered', data.food);
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

function useHitFood(): void {
    if (hitFood.value) {
        emit('food-hit', hitFood.value);
        close();
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="bg-cd-surface sm:max-w-lg">
            <DialogHeader>
                <DialogTitle class="font-sans">
                    {{
                        step === 'scan'
                            ? 'バーコードスキャン'
                            : step === 'ocr_capture'
                              ? '成分表を撮影'
                              : step === 'polling'
                                ? '照合中...'
                                : step === 'confirm'
                                  ? '栄養情報の確認'
                                  : '登録済み食品'
                    }}
                </DialogTitle>
                <DialogDescription class="font-sans text-sm text-cd-ink-muted">
                    {{
                        step === 'scan'
                            ? 'カメラでバーコードを読み取るか、番号を直接入力してください。'
                            : step === 'ocr_capture'
                              ? '栄養成分表示を撮影すると AI が読み取ります。'
                              : step === 'polling'
                                ? 'データベースを照合しています...'
                                : step === 'confirm'
                                  ? '内容を確認・編集して保存してください。値は自由に修正できます。'
                                  : 'この商品は既にマイ食品に登録されています。'
                    }}
                </DialogDescription>
            </DialogHeader>

            <p
                v-if="errorMessage"
                class="rounded-lg bg-destructive/10 px-3 py-2 font-sans text-sm text-destructive"
            >
                {{ errorMessage }}
            </p>

            <!-- Scan step -->
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
                    <div v-if="!scanning" class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-black/60">
                        <ScanLine :size="32" class="text-white/70" />
                        <Button
                            type="button"
                            size="sm"
                            class="font-sans"
                            @click="startCamera"
                        >
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

                <div class="flex flex-col gap-2 border-t border-cd-line pt-3">
                    <Button
                        v-if="lookupId"
                        type="button"
                        class="font-sans"
                        @click="startOcrForMiss"
                    >
                        <Camera :size="16" class="mr-1" />
                        この商品の成分表を撮影して登録
                    </Button>
                    <button
                        type="button"
                        class="font-sans text-xs text-cd-ink-muted underline-offset-2 hover:underline"
                        @click="startOcrWithoutBarcode"
                    >
                        バーコードがない商品は、成分表の撮影から登録できます
                    </button>
                </div>
            </div>

            <!-- OCR capture step -->
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

                <p class="font-sans text-xs text-cd-ink-muted">
                    成分表全体が明るく写るように撮影してください。読み取り結果は保存前に必ず確認できます。写真は解析後に破棄されます。
                </p>
            </div>

            <!-- Polling step -->
            <div v-if="step === 'polling'" class="flex flex-col items-center gap-4 py-8">
                <Loader2 :size="32" class="animate-spin text-primary" />
                <p class="font-sans text-sm text-cd-ink-muted">
                    照合しています…（読み取りには数十秒かかることがあります）
                </p>
            </div>

            <!-- Confirm step -->
            <div v-if="step === 'confirm'" class="flex flex-col gap-3">
                <p
                    v-if="lookupSource"
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
                        · {{ lookupResult.per === 'serving' ? '1食分' : '100g あたり' }}
                    </template>
                </p>

                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2 flex flex-col gap-1">
                        <Label class="font-sans text-xs">商品名 <span class="text-destructive">*</span></Label>
                        <Input v-model="confirmForm.name" type="text" maxlength="100" />
                    </div>
                    <div class="col-span-2 flex flex-col gap-1">
                        <Label class="font-sans text-xs">1サービング <span class="text-destructive">*</span></Label>
                        <Input v-model="confirmForm.serving_label" type="text" maxlength="50" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label class="font-sans text-xs">kcal <span class="text-destructive">*</span></Label>
                        <Input v-model="confirmForm.kcal" type="number" min="0" max="9999" step="0.1" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label class="font-sans text-xs">P (g) <span class="text-destructive">*</span></Label>
                        <Input v-model="confirmForm.protein_g" type="number" min="0" max="999" step="0.1" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label class="font-sans text-xs">F (g) <span class="text-destructive">*</span></Label>
                        <Input v-model="confirmForm.fat_g" type="number" min="0" max="999" step="0.1" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label class="font-sans text-xs">C (g) <span class="text-destructive">*</span></Label>
                        <Input v-model="confirmForm.carb_g" type="number" min="0" max="999" step="0.1" />
                    </div>
                </div>
            </div>

            <!-- Hit step -->
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
            </div>

            <DialogFooter v-if="step === 'confirm' || step === 'hit'">
                <Button
                    type="button"
                    variant="outline"
                    class="font-sans"
                    @click="close"
                >
                    キャンセル
                </Button>
                <Button
                    v-if="step === 'confirm'"
                    type="button"
                    class="font-sans"
                    :disabled="saving || !canConfirm"
                    @click="confirmAndSave"
                >
                    マイ食品に保存
                </Button>
                <Button
                    v-if="step === 'hit'"
                    type="button"
                    class="font-sans"
                    @click="useHitFood"
                >
                    この食品を使う
                </Button>
            </DialogFooter>

            <DialogFooter v-if="step === 'scan'">
                <Button
                    type="button"
                    variant="outline"
                    class="font-sans"
                    @click="close"
                >
                    閉じる
                </Button>
            </DialogFooter>

            <DialogFooter v-if="step === 'ocr_capture'">
                <Button
                    type="button"
                    variant="outline"
                    class="font-sans"
                    @click="step = 'scan'"
                >
                    バーコード入力に戻る
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
