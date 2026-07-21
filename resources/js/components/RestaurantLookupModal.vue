<script setup lang="ts">
import { Camera, Loader2, Store } from '@lucide/vue';
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
import { downscaleLabelImage } from '@/composables/useLabelImageCapture';
import { apiFetch, ApiError } from '@/lib/apiFetch';
import type { FoodItem } from '@/types/routine';

type Step = 'choose' | 'photo_capture' | 'menu_input' | 'polling' | 'confirm';

interface LookupResult {
    name: string;
    serving_label: string;
    per: string;
    kcal: number | null;
    protein_g: number | null;
    fat_g: number | null;
    carb_g: number | null;
}

interface Props {
    open: boolean;
    initialStep?: 'photo_capture' | 'menu_input';
}

interface Emits {
    (e: 'update:open', value: boolean): void;
    (e: 'food-registered', food: FoodItem): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

const step = ref<Step>('choose');
const lookupId = ref<string | null>(null);
const lookupResult = ref<LookupResult | null>(null);
const saving = ref(false);
const errorMessage = ref<string | null>(null);

const photoFile = ref<File | null>(null);
const photoPreviewUrl = ref<string | null>(null);
const photoFileInput = ref<HTMLInputElement | null>(null);

const menuForm = ref({
    store_name: '',
    menu_name: '',
});

const confirmForm = ref({
    name: '',
    serving_label: '',
    kcal: '',
    protein_g: '',
    fat_g: '',
    carb_g: '',
});

let pollTimer: ReturnType<typeof setTimeout> | null = null;

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

const canSubmitMenu = computed(() => {
    return (
        menuForm.value.store_name.trim() !== '' &&
        menuForm.value.menu_name.trim() !== ''
    );
});

watch(
    () => props.open,
    (open) => {
        if (open) {
            reset();

            if (props.initialStep) {
                step.value = props.initialStep;
            }
        } else {
            cleanup();
        }
    },
);

function reset(): void {
    step.value = 'choose';
    lookupId.value = null;
    lookupResult.value = null;
    saving.value = false;
    errorMessage.value = null;
    clearPhotoFile();
    menuForm.value = { store_name: '', menu_name: '' };
    confirmForm.value = {
        name: '',
        serving_label: '',
        kcal: '',
        protein_g: '',
        fat_g: '',
        carb_g: '',
    };
}

function cleanup(): void {
    clearPollTimer();
    clearPhotoFile();
}

function clearPhotoFile(): void {
    if (photoPreviewUrl.value !== null) {
        URL.revokeObjectURL(photoPreviewUrl.value);
    }

    photoFile.value = null;
    photoPreviewUrl.value = null;
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

function openPhotoPicker(): void {
    photoFileInput.value?.click();
}

function onPhotoFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    input.value = '';

    if (!file) {
        return;
    }

    clearPhotoFile();
    photoFile.value = file;
    photoPreviewUrl.value = URL.createObjectURL(file);
}

async function submitPhoto(): Promise<void> {
    if (!photoFile.value || saving.value) {
        return;
    }

    saving.value = true;
    errorMessage.value = null;

    try {
        const image = await downscaleLabelImage(photoFile.value);
        const form = new FormData();
        form.append('image', image);

        const data = await apiFetch<{ status: string; lookup_id: string }>(
            '/meals/photo-estimate',
            { method: 'POST', body: form },
        );

        lookupId.value = data.lookup_id;
        clearPhotoFile();
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
        } else {
            errorMessage.value = '送信に失敗しました。もう一度お試しください。';
        }
    } finally {
        saving.value = false;
    }
}

async function submitMenu(): Promise<void> {
    if (!canSubmitMenu.value || saving.value) {
        return;
    }

    saving.value = true;
    errorMessage.value = null;

    try {
        const data = await apiFetch<{ status: string; lookup_id: string }>(
            '/meals/menu-estimate',
            {
                method: 'POST',
                body: JSON.stringify({
                    store_name: menuForm.value.store_name.trim(),
                    menu_name: menuForm.value.menu_name.trim(),
                }),
            },
        );

        lookupId.value = data.lookup_id;
        step.value = 'polling';
        startPolling();
    } catch (e) {
        if (e instanceof ApiError && e.status === 422) {
            const body = e.body as {
                message?: string;
                errors?: Record<string, string[]>;
            };
            const firstErr = Object.values(body.errors ?? {})[0];
            errorMessage.value =
                firstErr?.[0] ?? body.message ?? '入力内容を確認してください。';
        } else {
            errorMessage.value = '送信に失敗しました。もう一度お試しください。';
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
            prefillConfirmForm(data.result);
            step.value = 'confirm';

            return;
        }

        if (data.status === 'failed') {
            if (
                data.error_code === 'photo_unrecognizable' ||
                data.error_code === 'menu_unknown'
            ) {
                errorMessage.value =
                    '推定できませんでした。別の写真を試すか、直接入力で登録してください。';
            } else if (
                data.error_code === 'photo_quota_exceeded' ||
                data.error_code === 'menu_quota_exceeded'
            ) {
                errorMessage.value =
                    '今月のAI利用枠を使い切りました。直接入力で登録してください。';
            } else {
                errorMessage.value =
                    '推定に失敗しました。もう一度お試しください。';
            }

            step.value = 'choose';

            return;
        }

        pollTimer = setTimeout(() => void pollLookup(), 1000);
    } catch {
        errorMessage.value = '通信エラーが発生しました。';
        step.value = 'choose';
    }
}

function prefillConfirmForm(result: LookupResult): void {
    confirmForm.value = {
        name: result.name ?? '',
        serving_label: result.serving_label ?? '1人前',
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
</script>

<template>
    <Dialog :open="open" @update:open="(v) => emit('update:open', v)">
        <DialogContent class="bg-cd-surface sm:max-w-lg">
            <DialogHeader>
                <DialogTitle class="font-sans">
                    {{
                        step === 'choose'
                            ? '外食メニューの栄養推定'
                            : step === 'photo_capture'
                              ? '料理を撮影'
                              : step === 'menu_input'
                                ? '店名・メニュー名を入力'
                                : step === 'polling'
                                  ? 'AI が推定中...'
                                  : '栄養情報の確認'
                    }}
                </DialogTitle>
                <DialogDescription class="font-sans text-sm text-cd-ink-muted">
                    {{
                        step === 'choose'
                            ? '写真またはメニュー名から栄養成分を AI が推定します。'
                            : step === 'photo_capture'
                              ? '料理の写真を撮影すると AI が栄養成分を推定します。'
                              : step === 'menu_input'
                                ? '店名とメニュー名を入力すると AI が栄養成分を推定します。'
                                : step === 'polling'
                                  ? 'AI が栄養成分を推定しています...'
                                  : '推定値を確認・編集して保存してください。'
                    }}
                </DialogDescription>
            </DialogHeader>

            <p
                v-if="errorMessage"
                class="rounded-lg bg-destructive/10 px-3 py-2 font-sans text-sm text-destructive"
            >
                {{ errorMessage }}
            </p>

            <!-- Choose step -->
            <div v-if="step === 'choose'" class="flex flex-col gap-3">
                <button
                    type="button"
                    class="flex items-center gap-4 rounded-xl border border-cd-line px-4 py-4 text-left transition-colors hover:border-primary/40 hover:bg-primary/5"
                    @click="step = 'photo_capture'"
                >
                    <Camera :size="24" :stroke-width="1.6" class="shrink-0 text-primary" />
                    <div>
                        <p class="font-sans text-sm font-semibold text-cd-ink">料理を撮影</p>
                        <p class="mt-0.5 font-sans text-xs text-cd-ink-muted">
                            写真から栄養成分を AI が推定します
                        </p>
                    </div>
                </button>
                <button
                    type="button"
                    class="flex items-center gap-4 rounded-xl border border-cd-line px-4 py-4 text-left transition-colors hover:border-primary/40 hover:bg-primary/5"
                    @click="step = 'menu_input'"
                >
                    <Store :size="24" :stroke-width="1.6" class="shrink-0 text-primary" />
                    <div>
                        <p class="font-sans text-sm font-semibold text-cd-ink">店名・メニュー名で検索</p>
                        <p class="mt-0.5 font-sans text-xs text-cd-ink-muted">
                            店舗名とメニュー名から栄養成分を推定します
                        </p>
                    </div>
                </button>
            </div>

            <!-- Photo capture step -->
            <div v-if="step === 'photo_capture'" class="flex flex-col gap-4">
                <input
                    ref="photoFileInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    capture="environment"
                    class="hidden"
                    @change="onPhotoFileSelected"
                />

                <div
                    v-if="photoPreviewUrl"
                    class="overflow-hidden rounded-xl"
                >
                    <img
                        :src="photoPreviewUrl"
                        alt="撮影した料理"
                        class="max-h-64 w-full object-contain"
                    />
                </div>

                <div
                    v-else
                    class="flex flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-cd-line px-4 py-12"
                >
                    <Camera :size="32" class="text-cd-ink-muted" />
                    <p class="font-sans text-sm text-cd-ink-muted">
                        料理の写真を撮影してください
                    </p>
                </div>

                <DialogFooter class="flex-col gap-2 sm:flex-row">
                    <Button
                        type="button"
                        variant="outline"
                        class="font-sans"
                        @click="step = 'choose'"
                    >
                        戻る
                    </Button>
                    <Button
                        v-if="!photoFile"
                        type="button"
                        class="font-sans"
                        @click="openPhotoPicker"
                    >
                        <Camera :size="14" :stroke-width="1.6" />
                        写真を撮影
                    </Button>
                    <template v-else>
                        <Button
                            type="button"
                            variant="outline"
                            class="font-sans"
                            @click="openPhotoPicker"
                        >
                            撮り直す
                        </Button>
                        <Button
                            type="button"
                            class="font-sans"
                            :disabled="saving"
                            @click="submitPhoto"
                        >
                            <Loader2 v-if="saving" :size="14" class="animate-spin" />
                            推定する
                        </Button>
                    </template>
                </DialogFooter>
            </div>

            <!-- Menu input step -->
            <div v-if="step === 'menu_input'" class="flex flex-col gap-4">
                <div class="flex flex-col gap-1">
                    <Label class="font-sans text-xs">店名</Label>
                    <Input
                        v-model="menuForm.store_name"
                        type="text"
                        maxlength="100"
                        placeholder="例: 一蘭、松屋、CoCo壱番屋"
                    />
                </div>
                <div class="flex flex-col gap-1">
                    <Label class="font-sans text-xs">メニュー名</Label>
                    <Input
                        v-model="menuForm.menu_name"
                        type="text"
                        maxlength="100"
                        placeholder="例: 味噌ラーメン、牛めし並盛"
                    />
                </div>

                <DialogFooter class="flex-col gap-2 sm:flex-row">
                    <Button
                        type="button"
                        variant="outline"
                        class="font-sans"
                        @click="step = 'choose'"
                    >
                        戻る
                    </Button>
                    <Button
                        type="button"
                        class="font-sans"
                        :disabled="saving || !canSubmitMenu"
                        @click="submitMenu"
                    >
                        <Loader2 v-if="saving" :size="14" class="animate-spin" />
                        推定する
                    </Button>
                </DialogFooter>
            </div>

            <!-- Polling step -->
            <div v-if="step === 'polling'" class="flex flex-col items-center gap-4 py-8">
                <Loader2 :size="32" class="animate-spin text-primary" />
                <p class="font-sans text-sm text-cd-ink-muted">
                    AI が栄養成分を推定しています。少々お待ちください...
                </p>
            </div>

            <!-- Confirm step -->
            <div v-if="step === 'confirm'" class="flex flex-col gap-4">
                <div class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 dark:border-amber-700 dark:bg-amber-950/40">
                    <p class="font-sans text-xs font-medium text-amber-800 dark:text-amber-300">
                        AI推定値です。実際の値と異なる場合があります。確認してから保存してください。
                    </p>
                </div>

                <div class="flex flex-col gap-3">
                    <div class="flex flex-col gap-1">
                        <Label class="font-sans text-xs">名前</Label>
                        <Input v-model="confirmForm.name" type="text" maxlength="100" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label class="font-sans text-xs">サービングラベル</Label>
                        <Input
                            v-model="confirmForm.serving_label"
                            type="text"
                            maxlength="50"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="flex flex-col gap-1">
                            <Label class="font-sans text-xs">kcal</Label>
                            <Input
                                v-model="confirmForm.kcal"
                                type="number"
                                min="0"
                                step="0.1"
                            />
                        </div>
                        <div class="flex flex-col gap-1">
                            <Label class="font-sans text-xs">P (g)</Label>
                            <Input
                                v-model="confirmForm.protein_g"
                                type="number"
                                min="0"
                                step="0.1"
                            />
                        </div>
                        <div class="flex flex-col gap-1">
                            <Label class="font-sans text-xs">F (g)</Label>
                            <Input
                                v-model="confirmForm.fat_g"
                                type="number"
                                min="0"
                                step="0.1"
                            />
                        </div>
                        <div class="flex flex-col gap-1">
                            <Label class="font-sans text-xs">C (g)</Label>
                            <Input
                                v-model="confirmForm.carb_g"
                                type="number"
                                min="0"
                                step="0.1"
                            />
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        class="font-sans"
                        @click="close"
                    >
                        キャンセル
                    </Button>
                    <Button
                        type="button"
                        class="font-sans"
                        :disabled="saving || !canConfirm"
                        @click="confirmAndSave"
                    >
                        <Loader2 v-if="saving" :size="14" class="animate-spin" />
                        マイ食品に保存
                    </Button>
                </DialogFooter>
            </div>
        </DialogContent>
    </Dialog>
</template>
