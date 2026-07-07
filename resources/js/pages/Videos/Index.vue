<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Clapperboard,
    Play,
    Plus,
    Trash2,
    Upload,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import PageTitleOrnament from '@/components/PageTitleOrnament.vue';
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
import { useVideoUpload } from '@/composables/useVideoUpload';
import { apiFetch } from '@/lib/apiFetch';
import {
    formatVideoDuration,
    videoStatusLabels,
} from '@/lib/trainingConstants';
import type { Video } from '@/types/training';

interface Props {
    videos: Video[] | { data: Video[] };
}

const props = defineProps<Props>();

const showUploadModal = ref(false);
const showPlaybackModal = ref(false);
const uploadTitle = ref('');
const selectedFile = ref<File | null>(null);
const playbackUrl = ref<string | null>(null);
const playbackVideo = ref<Video | null>(null);
const playbackLoading = ref(false);

const {
    state: uploadState,
    progress: uploadProgress,
    errorMessage: uploadError,
    upload,
    cancel: cancelUpload,
    reset: resetUpload,
} = useVideoUpload({
    onSuccess: () => {
        showUploadModal.value = false;
        resetUploadForm();
        router.reload({ only: ['videos'] });
    },
});

const videoList = computed(() => {
    if (Array.isArray(props.videos)) {
        return props.videos;
    }

    return props.videos.data ?? [];
});

const isMovSelected = computed(
    () => selectedFile.value?.type === 'video/quicktime',
);

const isUploading = computed(() =>
    ['preparing', 'uploading', 'finalizing'].includes(uploadState.value),
);

function resetUploadForm(): void {
    uploadTitle.value = '';
    selectedFile.value = null;
    resetUpload();
}

function openUploadModal(): void {
    resetUploadForm();
    showUploadModal.value = true;
}

function onFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    selectedFile.value = input.files?.[0] ?? null;
}

async function submitUpload(): Promise<void> {
    if (!selectedFile.value || !uploadTitle.value.trim()) {
        return;
    }

    await upload(selectedFile.value, uploadTitle.value.trim());
}

function closeUploadModal(open: boolean): void {
    if (!open && isUploading.value) {
        cancelUpload();
    }

    showUploadModal.value = open;

    if (!open) {
        resetUploadForm();
    }
}

async function openPlayback(video: Video): Promise<void> {
    if (video.status !== 'ready') {
        return;
    }

    playbackVideo.value = video;
    playbackUrl.value = null;
    playbackLoading.value = true;
    showPlaybackModal.value = true;

    try {
        const result = await apiFetch<{ url: string }>(
            `/videos/${video.id}/stream-url`,
        );
        playbackUrl.value = result.url;
    } catch {
        playbackUrl.value = null;
    } finally {
        playbackLoading.value = false;
    }
}

async function deleteVideo(video: Video): Promise<void> {
    if (!confirm(`「${video.title}」を削除しますか？`)) {
        return;
    }

    await apiFetch(`/videos/${video.id}`, { method: 'DELETE' });
    router.reload({ only: ['videos'] });
}
</script>

<template>
    <Head title="動画" />

    <div
        class="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4 md:px-6 md:pb-6"
    >
        <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6">
            <div class="flex items-start justify-between gap-4">
                <PageTitleOrnament
                    title="動画"
                    subtitle="フォーム確認用の短尺動画を管理し、種目に添付できます。"
                    align="left"
                />

                <Button
                    type="button"
                    class="mt-2 shrink-0 font-sans tracking-[0.08em]"
                    @click="openUploadModal"
                >
                    <Upload :size="16" :stroke-width="1.8" />
                    アップロード
                </Button>
            </div>

            <section
                aria-label="動画ライブラリ"
                class="cd-shadow-soft rounded-2xl border border-cd-line bg-cd-surface p-5"
            >
                <div
                    v-if="videoList.length === 0"
                    class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4"
                >
                    <div
                        v-for="n in 4"
                        :key="n"
                        class="flex aspect-video flex-col items-center justify-center rounded-xl border border-dashed border-cd-line/80 bg-white/40 text-cd-ink-muted"
                    >
                        <Clapperboard
                            :size="28"
                            :stroke-width="1.4"
                            class="opacity-40"
                        />
                        <span class="mt-2 font-sans text-xs">動画なし</span>
                    </div>
                </div>

                <ul
                    v-else
                    class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4"
                >
                    <li
                        v-for="video in videoList"
                        :key="video.id"
                        class="group relative overflow-hidden rounded-xl border border-cd-line/80 bg-white/50"
                    >
                        <button
                            type="button"
                            class="relative flex aspect-video w-full flex-col items-center justify-center bg-cd-dawn-soft/20 transition-colors hover:bg-cd-dawn-soft/30 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="video.status !== 'ready'"
                            @click="openPlayback(video)"
                        >
                            <Clapperboard
                                :size="32"
                                :stroke-width="1.4"
                                class="text-cd-ink-muted/50"
                            />
                            <Play
                                v-if="video.status === 'ready'"
                                :size="20"
                                :stroke-width="1.6"
                                class="absolute text-primary opacity-0 transition-opacity group-hover:opacity-100"
                            />
                            <span
                                v-if="video.duration_seconds"
                                class="absolute right-2 bottom-2 rounded bg-cd-ink/70 px-1.5 py-0.5 font-sans text-[0.65rem] text-white"
                            >
                                {{
                                    formatVideoDuration(video.duration_seconds)
                                }}
                            </span>
                        </button>

                        <div class="space-y-1 p-3">
                            <p
                                class="truncate font-serif text-sm tracking-[0.06em] text-cd-ink"
                            >
                                {{ video.title }}
                            </p>
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <span
                                    class="font-sans text-[0.65rem] text-cd-ink-muted"
                                >
                                    {{ videoStatusLabels[video.status] }}
                                </span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    :aria-label="`${video.title} を削除`"
                                    @click="deleteVideo(video)"
                                >
                                    <Trash2 :size="14" :stroke-width="1.6" />
                                </Button>
                            </div>
                        </div>
                    </li>
                </ul>
            </section>
        </div>
    </div>

    <Dialog :open="showUploadModal" @update:open="closeUploadModal">
        <DialogContent class="bg-cd-surface sm:max-w-md">
            <DialogHeader>
                <DialogTitle
                    class="font-serif text-lg tracking-[0.12em] text-cd-ink"
                >
                    動画をアップロード
                </DialogTitle>
                <DialogDescription class="font-sans text-sm text-cd-ink-muted">
                    mp4 / webm / mov 形式に対応しています。
                </DialogDescription>
            </DialogHeader>

            <div class="flex flex-col gap-3">
                <Input
                    v-model="uploadTitle"
                    placeholder="タイトル"
                    maxlength="100"
                    :disabled="isUploading"
                />

                <label
                    class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border border-dashed border-cd-line px-4 py-6 font-sans text-sm text-cd-ink-muted transition-colors hover:border-cd-line hover:bg-white/40"
                >
                    <Plus :size="20" :stroke-width="1.6" />
                    <span>{{
                        selectedFile?.name ?? 'ファイルを選択'
                    }}</span>
                    <input
                        type="file"
                        accept="video/mp4,video/webm,video/quicktime"
                        class="sr-only"
                        :disabled="isUploading"
                        @change="onFileChange"
                    />
                </label>

                <div
                    v-if="isMovSelected"
                    class="flex items-start gap-2 rounded-lg border border-cd-sunrise/30 bg-cd-sunrise/10 px-3 py-2 font-sans text-xs text-cd-ink"
                >
                    <AlertTriangle
                        :size="14"
                        :stroke-width="1.6"
                        class="mt-0.5 shrink-0 text-cd-sunrise"
                    />
                    MOV 形式はブラウザによって再生できない場合があります。mp4 での保存を推奨します。
                </div>

                <div v-if="isUploading" class="space-y-1">
                    <div
                        class="h-2 overflow-hidden rounded-full bg-muted"
                        role="progressbar"
                        :aria-valuenow="uploadProgress.percent"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    >
                        <div
                            class="h-full bg-primary transition-all"
                            :style="{ width: `${uploadProgress.percent}%` }"
                        />
                    </div>
                    <p class="font-sans text-xs text-cd-ink-muted">
                        {{ uploadProgress.percent }}%
                    </p>
                </div>

                <p
                    v-if="uploadError"
                    class="font-sans text-xs text-destructive"
                >
                    {{ uploadError }}
                </p>
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="ghost"
                    :disabled="isUploading && uploadState !== 'uploading'"
                    @click="closeUploadModal(false)"
                >
                    キャンセル
                </Button>
                <Button
                    type="button"
                    :disabled="
                        isUploading ||
                        !selectedFile ||
                        !uploadTitle.trim()
                    "
                    @click="submitUpload"
                >
                    アップロード
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog
        :open="showPlaybackModal"
        @update:open="(v) => (showPlaybackModal = v)"
    >
        <DialogContent class="bg-cd-surface sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle
                    class="font-serif text-lg tracking-[0.12em] text-cd-ink"
                >
                    {{ playbackVideo?.title }}
                </DialogTitle>
            </DialogHeader>

            <div
                class="flex aspect-video items-center justify-center rounded-xl bg-cd-ink/5"
            >
                <p
                    v-if="playbackLoading"
                    class="font-sans text-sm text-cd-ink-muted"
                >
                    読み込み中…
                </p>
                <video
                    v-else-if="playbackUrl"
                    :src="playbackUrl"
                    controls
                    class="max-h-full max-w-full rounded-xl"
                />
                <p v-else class="font-sans text-sm text-cd-ink-muted">
                    再生 URL を取得できませんでした。
                </p>
            </div>
        </DialogContent>
    </Dialog>
</template>
