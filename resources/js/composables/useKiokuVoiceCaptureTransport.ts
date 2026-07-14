import { apiFetch } from '@/lib/apiFetch';
import { fileExtensionForAudioMime } from '@/lib/kiokuAudioRecorder.mjs';
import type {
    CaptureQueueItem,
    CaptureSendResult,
} from '@/lib/kiokuCaptureQueue.mjs';
import { voice } from '@/routes/kioku/captures';

type VoiceCaptureResponse = {
    memory: { id: string };
    created: boolean;
};

/**
 * Multipart transport for queued voice captures. Split from the queue
 * composable so the audio path lazy-loads only when a voice item syncs.
 */
export async function sendVoiceCapture(
    item: CaptureQueueItem,
): Promise<CaptureSendResult> {
    if (item.audioBlob === null) {
        // Unrecoverable payload — surface as a permanent rejection so the
        // queue stops retrying but keeps the item visible.
        throw Object.assign(new Error('voice item has no audio blob'), {
            status: 422,
        });
    }

    const formData = new FormData();
    formData.append('client_capture_id', item.clientCaptureId);
    formData.append(
        'audio',
        item.audioBlob,
        `capture.${fileExtensionForAudioMime(item.audioMimeType)}`,
    );
    formData.append('duration_ms', String(item.durationMs ?? 0));
    formData.append('captured_at', item.capturedAt);

    const response = await apiFetch<VoiceCaptureResponse>(voice.url(), {
        method: 'POST',
        body: formData,
    });

    return {
        memoryId: response.memory.id,
        created: response.created,
    };
}
