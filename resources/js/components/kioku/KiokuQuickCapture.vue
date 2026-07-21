<script setup lang="ts">
import { CloudOff, Mic, Send } from '@lucide/vue';
import { computed, ref } from 'vue';
import VoiceCaptureOverlay from '@/components/kioku/VoiceCaptureOverlay.vue';
import { Button } from '@/components/ui/button';
import { useAudioRecorder } from '@/composables/useAudioRecorder';
import type { RecordedAudio } from '@/composables/useAudioRecorder';
import { useKiokuCaptureQueue } from '@/composables/useKiokuCaptureQueue';
import { KIOKU_MAX_RECORDING_MS } from '@/lib/kiokuAudioRecorder.mjs';
import { buildCaptureQueueItem } from '@/lib/kiokuCaptureQueue.mjs';

const props = defineProps<{
    /** client_capture_id values already present on the server response */
    serverCaptureIds: Set<string>;
}>();

const emit = defineEmits<{
    synced: [];
}>();

const draft = ref('');
const saving = ref(false);
const captureError = ref<string | null>(null);
let captureStartedAtMs: number | null = null;

const {
    pendingCaptures,
    markCaptureStarted,
    submitText,
    enqueueItem,
    discardRejected,
    onSynced,
} = useKiokuCaptureQueue();

onSynced(() => {
    emit('synced');
});

/** Device-only items awaiting server sync (hidden once the server copy shows). */
const pendingLocalCaptures = computed(() =>
    pendingCaptures.value.filter(
        (item) => !props.serverCaptureIds.has(item.clientCaptureId),
    ),
);

async function discardRejectedCapture(clientCaptureId: string): Promise<void> {
    const confirmed = window.confirm(
        'このキャプチャはサーバーに送れない形式です。端末から破棄しますか？原文・音声は復元できません。',
    );

    if (!confirmed) {
        return;
    }

    await discardRejected(clientCaptureId);
}

function onDraftFocus(): void {
    if (captureStartedAtMs === null && draft.value === '') {
        captureStartedAtMs = markCaptureStarted('manual');
    }
}

const recorder = useAudioRecorder({
    onAutoStop: (audio) => {
        void saveRecording(audio);
    },
});
const voiceError = ref<string | null>(null);
let voiceCaptureStartedAtMs: number | null = null;

const isRecording = computed(
    () =>
        recorder.state.value === 'recording' ||
        recorder.state.value === 'stopping',
);

async function startRecording(): Promise<void> {
    voiceError.value = null;

    if (!recorder.isSupported) {
        voiceError.value =
            'この環境では録音に対応していません。テキストで残してください。';

        return;
    }

    voiceCaptureStartedAtMs = markCaptureStarted('voice');
    const started = await recorder.start();

    if (!started) {
        voiceError.value = recorder.permissionDenied.value
            ? 'マイクが許可されていません。ブラウザ設定で許可するか、テキストで残してください。'
            : '録音を開始できませんでした。テキストで残してください。';
    }
}

async function stopRecording(): Promise<void> {
    const audio = await recorder.stop();

    if (audio !== null) {
        await saveRecording(audio);
    }
}

async function saveRecording(audio: RecordedAudio): Promise<void> {
    const item = buildCaptureQueueItem({
        clientCaptureId: crypto.randomUUID(),
        sourceType: 'voice',
        audioBlob: audio.blob,
        audioMimeType: audio.mimeType,
        durationMs: Math.max(1, Math.round(audio.durationMs)),
        capturedAt: new Date().toISOString(),
    });

    const result = await enqueueItem(item, voiceCaptureStartedAtMs);
    voiceCaptureStartedAtMs = null;

    if (result.outcome === 'failed') {
        voiceError.value = result.message;
    } else if (result.outcome === 'sent_directly') {
        emit('synced');
    }
}

function discardRecording(): void {
    recorder.discard();
    voiceCaptureStartedAtMs = null;
}

async function submitDraft(): Promise<void> {
    const content = draft.value.trim();

    if (!content || saving.value) {
        return;
    }

    saving.value = true;
    captureError.value = null;

    try {
        const result = await submitText(content, captureStartedAtMs);

        if (result.outcome === 'failed') {
            captureError.value = result.message;

            return;
        }

        draft.value = '';
        captureStartedAtMs = null;

        if (result.outcome === 'sent_directly') {
            emit('synced');
        }
    } finally {
        saving.value = false;
    }
}

defineExpose({
    pendingLocalCaptures,
});
</script>

<template>
    <div class="space-y-3">
        <section
            class="rounded-2xl border border-os-kioku/25 bg-os-kioku-paper p-4 shadow-[0_1px_3px_rgba(43,41,36,0.05)] sm:p-5"
        >
            <div
                class="mb-2.5 flex items-center gap-1.5 text-sm font-bold text-os-kioku"
            >
                なんでも、まずここへ
            </div>

            <VoiceCaptureOverlay
                v-if="isRecording"
                :elapsed-ms="recorder.elapsedMs.value"
                :max-duration-ms="KIOKU_MAX_RECORDING_MS"
                :stopping="recorder.state.value === 'stopping'"
                @stop="stopRecording"
                @discard="discardRecording"
            />

            <p
                v-if="voiceError"
                class="mb-2 text-xs leading-relaxed text-[#C05A48]"
                role="alert"
            >
                {{ voiceError }}
            </p>

            <form
                v-if="!isRecording"
                class="space-y-3"
                @submit.prevent="submitDraft"
            >
                <textarea
                    v-model="draft"
                    name="raw_content"
                    rows="5"
                    required
                    placeholder="思いついたこと、気づき、URL……"
                    class="min-h-28 w-full resize-y rounded-xl border border-os-line bg-os-kioku-bg px-3.5 py-3 text-[13.5px] leading-relaxed text-os-ink outline-none placeholder:text-[#B3AC99] focus-visible:ring-2 focus-visible:ring-os-kioku/35"
                    @focus="onDraftFocus"
                    @keydown.meta.enter.prevent="submitDraft"
                    @keydown.ctrl.enter.prevent="submitDraft"
                />
                <p
                    v-if="captureError"
                    class="text-xs leading-relaxed text-[#C05A48]"
                    role="alert"
                >
                    {{ captureError }}
                </p>
                <p class="text-[11.5px] leading-relaxed text-os-sub">
                    保存は即時。AI整理はあとから行います。
                </p>
                <div
                    class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end"
                >
                    <Button
                        type="button"
                        variant="outline"
                        class="h-12 w-full gap-2 rounded-xl border-os-kioku/30 text-[13.5px] font-bold text-os-kioku sm:h-11 sm:w-auto sm:min-w-36"
                        @click="startRecording"
                    >
                        <Mic :size="15" />
                        音声で残す
                    </Button>
                    <Button
                        type="submit"
                        class="h-12 w-full gap-2 rounded-xl bg-os-kioku text-[13.5px] font-bold text-white shadow-[0_3px_10px_rgba(62,86,136,0.28)] hover:bg-os-kioku/90 sm:h-11 sm:w-auto sm:min-w-36"
                        :disabled="saving || !draft.trim()"
                        :class="draft.trim() ? 'opacity-100' : 'opacity-40'"
                    >
                        <Send :size="15" />
                        保存する
                    </Button>
                </div>
            </form>
        </section>

        <div
            v-for="item in pendingLocalCaptures"
            :key="item.clientCaptureId"
            class="rounded-2xl border border-dashed border-os-kioku/40 bg-os-kioku-paper px-4 py-3 shadow-[0_1px_3px_rgba(43,41,36,0.05)]"
            role="status"
        >
            <div
                class="mb-1 flex items-center gap-1.5 text-[11.5px] font-bold text-os-sub"
            >
                <CloudOff :size="13" />
                端末に保存済み・同期待ち
                <span v-if="item.rejected">（送信できない形式）</span>
                <span v-else-if="item.retryCount > 0"
                    >（再試行 {{ item.retryCount }}回目待ち）</span
                >
            </div>
            <p class="line-clamp-2 text-[13px] leading-relaxed text-os-ink">
                {{
                    item.sourceType === 'voice'
                        ? `音声メモ（${Math.round((item.durationMs ?? 0) / 1000)}秒）`
                        : item.rawContent
                }}
            </p>
            <button
                v-if="item.rejected"
                type="button"
                class="mt-2 text-[11.5px] font-bold text-[#C05A48] hover:underline"
                @click="discardRejectedCapture(item.clientCaptureId)"
            >
                端末から破棄
            </button>
        </div>
    </div>
</template>
