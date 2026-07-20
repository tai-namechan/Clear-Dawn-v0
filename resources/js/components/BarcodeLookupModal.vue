<script setup lang="ts">
import { Loader2, ScanLine } from '@lucide/vue';
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
import { apiFetch, ApiError } from '@/lib/apiFetch';
import type { FoodItem } from '@/types/routine';

type Step = 'scan' | 'polling' | 'confirm' | 'hit';

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
    confirmForm.value = { name: '', serving_label: '', kcal: '', protein_g: '', fat_g: '', carb_g: '' };
}

function cleanup(): void {
    stopCamera();
    clearPollTimer();
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
            errorMessage.value = 'この商品はデータベースに見つかりませんでした。直接入力で登録してください。';
            step.value = 'scan';

            return;
        }

        if (data.status === 'failed') {
            errorMessage.value = '照合に失敗しました。もう一度スキャンしてください。';
            step.value = 'scan';

            return;
        }

        pollTimer = setTimeout(() => void pollLookup(), 2000);
    } catch {
        errorMessage.value = '通信エラーが発生しました。';
        step.value = 'scan';
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
                            : step === 'polling'
                              ? 'Open Food Facts からデータを取得しています...'
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
            </div>

            <!-- Polling step -->
            <div v-if="step === 'polling'" class="flex flex-col items-center gap-4 py-8">
                <Loader2 :size="32" class="animate-spin text-primary" />
                <p class="font-sans text-sm text-cd-ink-muted">
                    データベースを照合しています...
                </p>
            </div>

            <!-- Confirm step -->
            <div v-if="step === 'confirm'" class="flex flex-col gap-3">
                <p
                    v-if="lookupSource"
                    class="font-sans text-xs text-cd-ink-muted"
                >
                    出典: {{ lookupSource === 'openfoodfacts' ? 'Open Food Facts' : lookupSource }}
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
        </DialogContent>
    </Dialog>
</template>
