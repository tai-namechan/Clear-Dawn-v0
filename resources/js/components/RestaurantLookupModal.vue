<script setup lang="ts">
import { Camera, ChefHat, Loader2, RotateCcw, Store, Utensils } from '@lucide/vue';
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
const estimateSource = ref<'photo' | 'menu' | null>(null);
const lookupSource = ref<string | null>(null);

const photoFile = ref<File | null>(null);
const photoPreviewUrl = ref<string | null>(null);
const submittedPhotoUrl = ref<string | null>(null);
const photoFileInput = ref<HTMLInputElement | null>(null);

const menuForm = ref({
    store_name: '',
    menu_name: '',
});
const submittedMenu = ref({ store_name: '', menu_name: '' });

const confirmForm = ref({
    name: '',
    serving_label: '',
    kcal: '',
    protein_g: '',
    fat_g: '',
    carb_g: '',
});

const showDetails = ref(false);

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

const pfcTotal = computed(() => {
    const p = Number(confirmForm.value.protein_g) || 0;
    const f = Number(confirmForm.value.fat_g) || 0;
    const c = Number(confirmForm.value.carb_g) || 0;

    return p + f + c;
});

const pfcRatios = computed(() => {
    const total = pfcTotal.value;

    if (total === 0) {
        return { p: 33, f: 33, c: 34 };
    }

    const p = Number(confirmForm.value.protein_g) || 0;
    const f = Number(confirmForm.value.fat_g) || 0;
    const c = Number(confirmForm.value.carb_g) || 0;

    return {
        p: Math.round((p / total) * 100),
        f: Math.round((f / total) * 100),
        c: Math.round((c / total) * 100),
    };
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
    estimateSource.value = null;
    lookupSource.value = null;
    showDetails.value = false;
    clearPhotoFile();
    submittedPhotoUrl.value = null;
    menuForm.value = { store_name: '', menu_name: '' };
    submittedMenu.value = { store_name: '', menu_name: '' };
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

    if (submittedPhotoUrl.value !== null) {
        URL.revokeObjectURL(submittedPhotoUrl.value);
        submittedPhotoUrl.value = null;
    }
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
        estimateSource.value = 'photo';
        submittedPhotoUrl.value = photoPreviewUrl.value;
        photoPreviewUrl.value = null;
        photoFile.value = null;
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
        const data = await apiFetch<{
            status: string;
            lookup_id?: string;
            food?: FoodItem;
        }>(
            '/meals/menu-estimate',
            {
                method: 'POST',
                body: JSON.stringify({
                    store_name: menuForm.value.store_name.trim(),
                    menu_name: menuForm.value.menu_name.trim(),
                }),
            },
        );

        estimateSource.value = 'menu';
        submittedMenu.value = {
            store_name: menuForm.value.store_name.trim(),
            menu_name: menuForm.value.menu_name.trim(),
        };

        if (data.status === 'hit' && data.food) {
            emit('food-registered', data.food);
            close();

            return;
        }

        lookupId.value = data.lookup_id!;
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
            lookupSource.value = data.source ?? null;
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

function retryEstimate(): void {
    errorMessage.value = null;
    showDetails.value = false;

    if (estimateSource.value === 'photo') {
        step.value = 'photo_capture';
    } else {
        step.value = 'menu_input';
    }
}

function formatNum(value: string | number): string {
    return Number(value).toLocaleString('ja-JP', {
        maximumFractionDigits: 1,
    });
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
        <DialogContent
            class="bg-cd-surface sm:max-w-lg"
            :class="step === 'confirm' ? 'p-0 gap-0' : ''"
        >
            <!-- Header for non-confirm steps -->
            <DialogHeader v-if="step !== 'confirm'">
                <DialogTitle class="font-sans">
                    {{
                        step === 'choose'
                            ? '外食メニューの栄養推定'
                            : step === 'photo_capture'
                              ? '料理の写真を選択'
                              : step === 'menu_input'
                                ? '店名・メニュー名を入力'
                                : 'AI が推定中...'
                    }}
                </DialogTitle>
                <DialogDescription class="font-sans text-sm text-cd-ink-muted">
                    {{
                        step === 'choose'
                            ? '写真またはメニュー名から栄養成分を AI が推定します。'
                            : step === 'photo_capture'
                              ? '料理の写真を撮影または選択すると AI が栄養成分を推定します。'
                              : step === 'menu_input'
                                ? '店名とメニュー名を入力すると AI が栄養成分を推定します。'
                                : 'AI が栄養成分を推定しています...'
                    }}
                </DialogDescription>
            </DialogHeader>

            <p
                v-if="errorMessage && step !== 'confirm'"
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
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                        <Camera :size="22" :stroke-width="1.6" class="text-primary" />
                    </div>
                    <div>
                        <p class="font-sans text-sm font-semibold text-cd-ink">料理の写真</p>
                        <p class="mt-0.5 font-sans text-xs text-cd-ink-muted">
                            撮影またはフォルダから選択して AI が推定
                        </p>
                    </div>
                </button>
                <button
                    type="button"
                    class="flex items-center gap-4 rounded-xl border border-cd-line px-4 py-4 text-left transition-colors hover:border-primary/40 hover:bg-primary/5"
                    @click="step = 'menu_input'"
                >
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                        <Store :size="22" :stroke-width="1.6" class="text-primary" />
                    </div>
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
                    class="hidden"
                    @change="onPhotoFileSelected"
                />

                <div
                    v-if="photoPreviewUrl"
                    class="overflow-hidden rounded-xl border border-cd-line"
                >
                    <img
                        :src="photoPreviewUrl"
                        alt="撮影した料理"
                        class="max-h-64 w-full object-contain"
                    />
                </div>

                <div
                    v-else
                    class="flex flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-cd-line px-4 py-12 transition-colors hover:border-primary/30 hover:bg-primary/5"
                    role="button"
                    tabindex="0"
                    @click="openPhotoPicker"
                    @keydown.enter="openPhotoPicker"
                >
                    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-primary/10">
                        <Camera :size="28" class="text-primary" />
                    </div>
                    <div class="text-center">
                        <p class="font-sans text-sm font-medium text-cd-ink">
                            タップして撮影・選択
                        </p>
                        <p class="mt-1 font-sans text-xs text-cd-ink-muted">
                            料理全体が写るように撮ってください
                        </p>
                    </div>
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
                    <template v-if="photoFile">
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
                            <Utensils v-else :size="14" :stroke-width="1.6" />
                            AI で推定する
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
                        <ChefHat v-else :size="14" :stroke-width="1.6" />
                        AI で推定する
                    </Button>
                </DialogFooter>
            </div>

            <!-- Polling step -->
            <div v-if="step === 'polling'" class="flex flex-col items-center gap-5 py-10">
                <div class="relative">
                    <div class="h-16 w-16 animate-spin rounded-full border-4 border-primary/20 border-t-primary" />
                    <Utensils :size="20" class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 text-primary" />
                </div>
                <div class="text-center">
                    <p class="font-sans text-sm font-medium text-cd-ink">
                        AI が栄養成分を推定しています
                    </p>
                    <p class="mt-1 font-sans text-xs text-cd-ink-muted">
                        外食の調理法や食材量を考慮して推定します
                    </p>
                </div>
            </div>

            <!-- Confirm step — full-bleed visual layout -->
            <div v-if="step === 'confirm'" class="flex flex-col">
                <!-- Photo header or menu header -->
                <div
                    v-if="submittedPhotoUrl && estimateSource === 'photo'"
                    class="relative"
                >
                    <img
                        :src="submittedPhotoUrl"
                        alt="推定した料理"
                        class="max-h-52 w-full object-cover"
                    />
                    <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent" />
                    <div class="absolute bottom-0 left-0 right-0 px-5 pb-4">
                        <p class="font-sans text-lg font-bold text-white drop-shadow-sm">
                            {{ confirmForm.name || '料理名' }}
                        </p>
                        <p class="font-sans text-xs text-white/80">
                            {{ confirmForm.serving_label }}
                        </p>
                    </div>
                </div>

                <div
                    v-else-if="estimateSource === 'menu'"
                    class="bg-gradient-to-br from-primary/10 to-primary/5 px-5 py-5"
                >
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/15">
                            <Store :size="24" :stroke-width="1.5" class="text-primary" />
                        </div>
                        <div class="min-w-0">
                            <p class="font-sans text-lg font-bold text-cd-ink">
                                {{ confirmForm.name || submittedMenu.menu_name }}
                            </p>
                            <p class="font-sans text-xs text-cd-ink-muted">
                                {{ submittedMenu.store_name }} · {{ confirmForm.serving_label }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Kcal hero -->
                <div class="flex flex-col items-center gap-1 px-5 pt-5 pb-3">
                    <p class="font-sans text-xs font-medium tracking-wider text-cd-ink-muted">
                        合計
                    </p>
                    <p class="font-sans text-4xl font-extrabold tabular-nums text-cd-ink">
                        {{ formatNum(confirmForm.kcal || '0') }}
                        <span class="text-lg font-semibold text-cd-ink-muted">kcal</span>
                    </p>
                </div>

                <!-- PFC bar -->
                <div class="mx-5 flex h-3 overflow-hidden rounded-full">
                    <div
                        class="bg-cd-pfc-p transition-all"
                        :style="{ width: pfcRatios.p + '%' }"
                    />
                    <div
                        class="bg-cd-pfc-f transition-all"
                        :style="{ width: pfcRatios.f + '%' }"
                    />
                    <div
                        class="bg-cd-pfc-c transition-all"
                        :style="{ width: pfcRatios.c + '%' }"
                    />
                </div>

                <!-- PFC numbers -->
                <div class="mx-5 mt-3 grid grid-cols-3 gap-2">
                    <div class="rounded-xl bg-cd-pfc-p/10 px-3 py-3 text-center">
                        <p class="font-sans text-[11px] font-semibold text-cd-pfc-p">P</p>
                        <p class="mt-0.5 font-sans text-lg font-bold tabular-nums text-cd-ink">
                            {{ formatNum(confirmForm.protein_g || '0') }}
                            <span class="text-xs font-medium text-cd-ink-muted">g</span>
                        </p>
                    </div>
                    <div class="rounded-xl bg-cd-pfc-f/10 px-3 py-3 text-center">
                        <p class="font-sans text-[11px] font-semibold text-cd-pfc-f">F</p>
                        <p class="mt-0.5 font-sans text-lg font-bold tabular-nums text-cd-ink">
                            {{ formatNum(confirmForm.fat_g || '0') }}
                            <span class="text-xs font-medium text-cd-ink-muted">g</span>
                        </p>
                    </div>
                    <div class="rounded-xl bg-cd-pfc-c/10 px-3 py-3 text-center">
                        <p class="font-sans text-[11px] font-semibold text-cd-pfc-c">C</p>
                        <p class="mt-0.5 font-sans text-lg font-bold tabular-nums text-cd-ink">
                            {{ formatNum(confirmForm.carb_g || '0') }}
                            <span class="text-xs font-medium text-cd-ink-muted">g</span>
                        </p>
                    </div>
                </div>

                <!-- Source notice -->
                <div
                    v-if="lookupSource === 'nutrition_db'"
                    class="mx-5 mt-4 rounded-lg border border-emerald-200 bg-emerald-50/80 px-3 py-2 dark:border-emerald-800 dark:bg-emerald-950/30"
                >
                    <p class="font-sans text-[11px] leading-relaxed text-emerald-700 dark:text-emerald-400">
                        栄養データベースの値です。公式情報に基づいていますが、店舗や時期により異なる場合があります。
                    </p>
                </div>
                <div
                    v-else
                    class="mx-5 mt-4 rounded-lg border border-amber-200 bg-amber-50/80 px-3 py-2 dark:border-amber-800 dark:bg-amber-950/30"
                >
                    <p class="font-sans text-[11px] leading-relaxed text-amber-700 dark:text-amber-400">
                        AI推定値です。外食の油脂・調味料を考慮して推定していますが、実際と異なる場合があります。
                    </p>
                </div>

                <!-- Edit toggle -->
                <button
                    type="button"
                    class="mx-5 mt-3 flex items-center justify-center gap-1.5 font-sans text-xs font-medium text-primary"
                    @click="showDetails = !showDetails"
                >
                    {{ showDetails ? '編集を閉じる' : '値を編集する' }}
                </button>

                <!-- Editable fields (collapsed by default) -->
                <div
                    v-if="showDetails"
                    class="mx-5 mt-3 flex flex-col gap-3 rounded-xl border border-cd-line bg-cd-surface p-4"
                >
                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2 flex flex-col gap-1">
                            <Label class="font-sans text-xs">名前</Label>
                            <Input v-model="confirmForm.name" type="text" maxlength="100" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <Label class="font-sans text-xs">サービング</Label>
                            <Input
                                v-model="confirmForm.serving_label"
                                type="text"
                                maxlength="50"
                            />
                        </div>
                        <div class="flex flex-col gap-1">
                            <Label class="font-sans text-xs">kcal</Label>
                            <Input
                                v-model="confirmForm.kcal"
                                type="number"
                                min="0"
                                step="1"
                            />
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="flex flex-col gap-1">
                            <Label class="font-sans text-xs text-cd-pfc-p">P (g)</Label>
                            <Input
                                v-model="confirmForm.protein_g"
                                type="number"
                                min="0"
                                step="0.1"
                            />
                        </div>
                        <div class="flex flex-col gap-1">
                            <Label class="font-sans text-xs text-cd-pfc-f">F (g)</Label>
                            <Input
                                v-model="confirmForm.fat_g"
                                type="number"
                                min="0"
                                step="0.1"
                            />
                        </div>
                        <div class="flex flex-col gap-1">
                            <Label class="font-sans text-xs text-cd-pfc-c">C (g)</Label>
                            <Input
                                v-model="confirmForm.carb_g"
                                type="number"
                                min="0"
                                step="0.1"
                            />
                        </div>
                    </div>
                </div>

                <p
                    v-if="errorMessage"
                    class="mx-5 mt-3 rounded-lg bg-destructive/10 px-3 py-2 font-sans text-sm text-destructive"
                >
                    {{ errorMessage }}
                </p>

                <!-- Actions -->
                <div class="flex gap-2 p-5">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        :aria-label="'やり直す'"
                        @click="retryEstimate"
                    >
                        <RotateCcw :size="16" :stroke-width="1.6" />
                    </Button>
                    <Button
                        type="button"
                        class="flex-1 font-sans"
                        :disabled="saving || !canConfirm"
                        @click="confirmAndSave"
                    >
                        <Loader2 v-if="saving" :size="14" class="animate-spin" />
                        マイ食品に保存
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
