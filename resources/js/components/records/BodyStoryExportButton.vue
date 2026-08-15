<script setup lang="ts">
import { Download } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { apiFetch } from '@/lib/apiFetch';
import {
    downloadCanvasPng,
    renderBodyStoryPng,
} from '@/lib/bodyStory/renderStoryPng';
import type { BodyStoryExportPayload, BodyStoryKind } from '@/types/bodyStory';

interface Props {
    kind: BodyStoryKind;
    date: string;
    label: string;
}

const props = defineProps<Props>();

const open = ref(false);
const loading = ref(false);
const exporting = ref(false);
const errorMessage = ref<string | null>(null);
const previewUrl = ref<string | null>(null);
const payload = ref<BodyStoryExportPayload | null>(null);
const filename = ref('clear-dawn-story.png');
const canvasRef = ref<HTMLCanvasElement | null>(null);
const photoObjectUrl = ref<string | null>(null);
const photoImage = ref<HTMLImageElement | null>(null);

const title = computed(() => {
    if (props.kind === 'weekly') {
        return '週間の記録をStory画像にする';
    }

    if (props.kind === 'weight') {
        return '今日の体重をStory画像にする';
    }

    return '今日の食事をStory画像にする';
});

function revokePreview(): void {
    if (previewUrl.value !== null) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = null;
    }
}

function revokePhoto(): void {
    if (photoObjectUrl.value !== null) {
        URL.revokeObjectURL(photoObjectUrl.value);
        photoObjectUrl.value = null;
    }

    photoImage.value = null;
}

async function paintPreview(
    nextPayload: BodyStoryExportPayload,
): Promise<void> {
    const rendered = await renderBodyStoryPng(nextPayload, photoImage.value);
    canvasRef.value = rendered.canvas;
    filename.value = rendered.filename;

    await new Promise<void>((resolve, reject) => {
        rendered.canvas.toBlob((blob) => {
            if (blob === null) {
                reject(new Error('png blob was empty'));

                return;
            }

            revokePreview();
            previewUrl.value = URL.createObjectURL(blob);
            resolve();
        }, 'image/png');
    });
}

async function loadStory(): Promise<void> {
    loading.value = true;
    errorMessage.value = null;
    payload.value = null;
    revokePreview();

    try {
        const query = new URLSearchParams({
            kind: props.kind,
            date: props.date,
        });
        const data = await apiFetch<BodyStoryExportPayload>(
            `/records/story-export?${query.toString()}`,
        );
        payload.value = data;
        await paintPreview(data);
    } catch {
        errorMessage.value =
            '画像の作成に失敗しました。もう一度お試しください。';
    } finally {
        loading.value = false;
    }
}

function onOpenChange(next: boolean): void {
    open.value = next;

    if (next) {
        void loadStory();

        return;
    }

    errorMessage.value = null;
    revokePreview();
    revokePhoto();
    payload.value = null;
    canvasRef.value = null;
}

async function onPhotoChange(event: Event): Promise<void> {
    const input = event.target;

    if (!(input instanceof HTMLInputElement) || input.files === null) {
        return;
    }

    const file = input.files[0];

    if (file === undefined) {
        return;
    }

    revokePhoto();
    const objectUrl = URL.createObjectURL(file);
    photoObjectUrl.value = objectUrl;

    try {
        const image = await new Promise<HTMLImageElement>((resolve, reject) => {
            const loaded = new Image();
            loaded.onload = () => resolve(loaded);
            loaded.onerror = () => reject(new Error('photo failed to load'));
            loaded.src = objectUrl;
        });
        photoImage.value = image;

        if (payload.value !== null) {
            await paintPreview(payload.value);
        }
    } catch {
        errorMessage.value =
            '画像の作成に失敗しました。もう一度お試しください。';
    }
}

function download(): void {
    if (canvasRef.value === null || exporting.value) {
        return;
    }

    exporting.value = true;

    try {
        downloadCanvasPng(canvasRef.value, filename.value);
    } finally {
        window.setTimeout(() => {
            exporting.value = false;
        }, 400);
    }
}

watch(
    () => props.date,
    () => {
        if (open.value) {
            void loadStory();
        }
    },
);

onBeforeUnmount(() => {
    revokePreview();
    revokePhoto();
});
</script>

<template>
    <div>
        <Button
            type="button"
            variant="outline"
            size="sm"
            class="font-sans"
            @click="onOpenChange(true)"
        >
            <Download :size="14" :stroke-width="1.6" />
            {{ label }}
        </Button>

        <Dialog :open="open" @update:open="onOpenChange">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ title }}</DialogTitle>
                    <DialogDescription>
                        1080×1920 の PNG を保存して、Instagram
                        のストーリーへ貼れます。
                    </DialogDescription>
                </DialogHeader>

                <p
                    v-if="loading"
                    class="font-sans text-sm text-muted-foreground"
                >
                    Story画像を作成しています…
                </p>
                <p
                    v-else-if="errorMessage"
                    class="font-sans text-sm text-destructive"
                >
                    {{ errorMessage }}
                </p>
                <div v-else class="flex flex-col gap-3">
                    <img
                        v-if="previewUrl"
                        :src="previewUrl"
                        alt="Story画像プレビュー"
                        class="mx-auto w-full max-w-[270px] rounded-md border border-border"
                        width="1080"
                        height="1920"
                    />
                    <label
                        v-if="kind === 'weight'"
                        class="font-sans text-sm text-cd-ink"
                    >
                        体重計の写真（任意・保存しません）
                        <input
                            type="file"
                            accept="image/*"
                            capture="environment"
                            class="mt-2 block w-full text-xs"
                            @change="onPhotoChange"
                        />
                    </label>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        class="font-sans"
                        @click="onOpenChange(false)"
                    >
                        閉じる
                    </Button>
                    <Button
                        type="button"
                        class="font-sans"
                        :disabled="loading || exporting || previewUrl === null"
                        @click="download"
                    >
                        PNGをダウンロード
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
