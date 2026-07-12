import { onUnmounted, ref } from 'vue';
import type { Ref } from 'vue';
import {
    KIOKU_MAX_RECORDING_MS,
    pickSupportedAudioMimeType,
    shouldAutoStopRecording,
} from '@/lib/kiokuAudioRecorder.mjs';

export type AudioRecorderState =
    'idle' | 'requesting_permission' | 'recording' | 'stopping';

export type RecordedAudio = {
    blob: Blob;
    mimeType: string;
    durationMs: number;
};

export type UseAudioRecorderOptions = {
    maxDurationMs?: number;
    /** Called when the 3-minute cap forces a stop; the recording is saved. */
    onAutoStop?: (audio: RecordedAudio) => void;
};

export type UseAudioRecorderReturn = {
    state: Ref<AudioRecorderState>;
    elapsedMs: Ref<number>;
    isSupported: boolean;
    permissionDenied: Ref<boolean>;
    start: () => Promise<boolean>;
    stop: () => Promise<RecordedAudio | null>;
    discard: () => void;
};

/**
 * MediaRecorder wrapper for quick voice capture. Feature-detects the
 * container format; never assumes one. Stopping resolves with the Blob —
 * persistence is the caller's job (queue first, then upload).
 */
export function useAudioRecorder(
    options: UseAudioRecorderOptions = {},
): UseAudioRecorderReturn {
    const maxDurationMs = options.maxDurationMs ?? KIOKU_MAX_RECORDING_MS;

    const state = ref<AudioRecorderState>('idle');
    const elapsedMs = ref(0);
    const permissionDenied = ref(false);

    const isSupported =
        typeof window !== 'undefined' &&
        typeof MediaRecorder !== 'undefined' &&
        typeof navigator !== 'undefined' &&
        !!navigator.mediaDevices?.getUserMedia;

    let recorder: MediaRecorder | null = null;
    let stream: MediaStream | null = null;
    let chunks: BlobPart[] = [];
    let startedAtMs = 0;
    let timer: ReturnType<typeof setInterval> | null = null;
    let stopResolver: ((audio: RecordedAudio | null) => void) | null = null;
    let discarded = false;

    function clearTimer(): void {
        if (timer !== null) {
            clearInterval(timer);
            timer = null;
        }
    }

    function releaseStream(): void {
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
    }

    function buildResult(): RecordedAudio | null {
        if (discarded || chunks.length === 0) {
            return null;
        }

        const mimeType =
            recorder?.mimeType && recorder.mimeType !== ''
                ? recorder.mimeType
                : 'audio/webm';

        return {
            blob: new Blob(chunks, { type: mimeType }),
            mimeType,
            durationMs: Math.min(elapsedMs.value, maxDurationMs),
        };
    }

    function finalize(): void {
        clearTimer();
        releaseStream();

        const result = buildResult();
        const resolver = stopResolver;
        const wasAutoStop = state.value === 'recording';

        stopResolver = null;
        recorder = null;
        chunks = [];
        state.value = 'idle';

        if (resolver !== null) {
            resolver(result);
        } else if (wasAutoStop && result !== null) {
            options.onAutoStop?.(result);
        }
    }

    async function start(): Promise<boolean> {
        if (!isSupported || state.value !== 'idle') {
            return false;
        }

        state.value = 'requesting_permission';
        permissionDenied.value = false;

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                audio: true,
            });
        } catch {
            permissionDenied.value = true;
            state.value = 'idle';

            return false;
        }

        const mimeType = pickSupportedAudioMimeType((type) =>
            MediaRecorder.isTypeSupported(type),
        );

        try {
            recorder = mimeType
                ? new MediaRecorder(stream, { mimeType })
                : new MediaRecorder(stream);
        } catch {
            releaseStream();
            state.value = 'idle';

            return false;
        }

        chunks = [];
        discarded = false;
        elapsedMs.value = 0;
        startedAtMs = Date.now();

        recorder.ondataavailable = (event: BlobEvent) => {
            if (event.data.size > 0) {
                chunks.push(event.data);
            }
        };
        recorder.onstop = finalize;

        recorder.start(1_000);
        state.value = 'recording';

        timer = setInterval(() => {
            elapsedMs.value = Date.now() - startedAtMs;

            if (
                shouldAutoStopRecording(elapsedMs.value, maxDurationMs) &&
                recorder !== null &&
                recorder.state === 'recording'
            ) {
                // Cap reached: stop safely and hand the Blob to onAutoStop.
                recorder.stop();
            }
        }, 250);

        return true;
    }

    function stop(): Promise<RecordedAudio | null> {
        if (recorder === null || state.value !== 'recording') {
            return Promise.resolve(null);
        }

        state.value = 'stopping';

        return new Promise((resolve) => {
            stopResolver = resolve;
            recorder?.stop();
        });
    }

    function discard(): void {
        discarded = true;

        if (recorder !== null && recorder.state !== 'inactive') {
            recorder.stop();
        } else {
            finalize();
        }
    }

    onUnmounted(() => {
        if (recorder !== null && recorder.state !== 'inactive') {
            discarded = true;
            recorder.stop();
        }

        clearTimer();
        releaseStream();
    });

    return {
        state,
        elapsedMs,
        isSupported,
        permissionDenied,
        start,
        stop,
        discard,
    };
}
