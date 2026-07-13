import { onMounted, onUnmounted, ref } from 'vue';
import type { Ref } from 'vue';
import { apiFetch } from '@/lib/apiFetch';
import {
    createIndexedDbCaptureStorage,
    isIndexedDbAvailable,
} from '@/lib/kiokuCaptureDb';
import {
    buildCaptureQueueItem,
    createCaptureQueueEngine,
} from '@/lib/kiokuCaptureQueue.mjs';
import type {
    CaptureQueueEngine,
    CaptureQueueItem,
    CaptureSendResult,
} from '@/lib/kiokuCaptureQueue.mjs';
import { events, manual } from '@/routes/kioku/captures';

type CaptureEventName =
    | 'capture_started'
    | 'local_saved'
    | 'local_save_failed'
    | 'server_synced'
    | 'sync_failed';

type ManualCaptureResponse = {
    memory: { id: string };
    created: boolean;
};

export type SubmitTextResult =
    | { outcome: 'queued' }
    | { outcome: 'sent_directly' }
    | { outcome: 'failed'; message: string };

/**
 * Module-level singleton so remounts share one queue and one flush loop.
 * The queue survives reloads via IndexedDB; items leave it only after the
 * server acknowledged the capture (docs/product/kioku-quick-capture.md §7).
 */
const queueItems = ref<CaptureQueueItem[]>([]);
const syncListeners = new Set<() => void>();
let engine: CaptureQueueEngine | null = null;
let engineInitPromise: Promise<void> | null = null;
let onlineListenerBound = false;

function sendMetric(
    event: CaptureEventName,
    attributes: {
        source_type: 'manual' | 'voice';
        duration_ms?: number;
        retry_count?: number;
    },
): void {
    // Fire-and-forget: metrics must never block or fail a capture.
    void apiFetch(events.url(), {
        method: 'POST',
        body: JSON.stringify({ event, ...attributes }),
    }).catch(() => undefined);
}

async function sendCapture(item: CaptureQueueItem): Promise<CaptureSendResult> {
    if (item.sourceType === 'voice') {
        const { sendVoiceCapture } =
            await import('@/composables/useKiokuVoiceCaptureTransport');

        return sendVoiceCapture(item);
    }

    const response = await apiFetch<ManualCaptureResponse>(manual.url(), {
        method: 'POST',
        body: JSON.stringify({
            client_capture_id: item.clientCaptureId,
            raw_content: item.rawContent,
            captured_at: item.capturedAt,
        }),
    });

    return {
        memoryId: response.memory.id,
        created: response.created,
    };
}

function ensureEngine(): CaptureQueueEngine | null {
    if (typeof window === 'undefined' || !isIndexedDbAvailable()) {
        return null;
    }

    if (engine === null) {
        engine = createCaptureQueueEngine({
            storage: createIndexedDbCaptureStorage(),
            sendCapture,
            onChange: (items) => {
                queueItems.value = items;
            },
            onItemSynced: (item) => {
                sendMetric('server_synced', {
                    source_type: item.sourceType,
                    retry_count: item.retryCount,
                });
                syncListeners.forEach((listener) => listener());
            },
            onItemSyncFailed: (item) => {
                sendMetric('sync_failed', {
                    source_type: item.sourceType,
                    retry_count: item.retryCount + 1,
                });
            },
        });
    }

    return engine;
}

async function ensureInitialized(
    activeEngine: CaptureQueueEngine,
): Promise<void> {
    if (engineInitPromise === null) {
        engineInitPromise = activeEngine.init().catch((error: unknown) => {
            engineInitPromise = null;

            throw error;
        });
    }

    await engineInitPromise;
}

async function initAndFlush(): Promise<void> {
    const activeEngine = ensureEngine();

    if (activeEngine === null) {
        return;
    }

    await ensureInitialized(activeEngine);
    await activeEngine.flush();
}

export type UseKiokuCaptureQueueReturn = {
    /** Items persisted on this device but not yet confirmed by the server. */
    pendingCaptures: Ref<CaptureQueueItem[]>;
    markCaptureStarted: (sourceType: 'manual' | 'voice') => number;
    submitText: (
        rawContent: string,
        captureStartedAtMs: number | null,
    ) => Promise<SubmitTextResult>;
    enqueueItem: (
        item: CaptureQueueItem,
        captureStartedAtMs: number | null,
    ) => Promise<SubmitTextResult>;
    flush: () => Promise<void>;
    /** Drop a terminal-rejected item from IndexedDB after explicit user confirm. */
    discardRejected: (clientCaptureId: string) => Promise<boolean>;
    onSynced: (listener: () => void) => void;
};

export function useKiokuCaptureQueue(): UseKiokuCaptureQueueReturn {
    const localListeners: Array<() => void> = [];

    function markCaptureStarted(sourceType: 'manual' | 'voice'): number {
        sendMetric('capture_started', { source_type: sourceType });

        return Date.now();
    }

    /**
     * Durability-first submit: persist to IndexedDB, report success, then
     * sync in the background. Falls back to a direct network send when
     * IndexedDB is unavailable — and only then does a network failure
     * surface to the caller.
     */
    async function enqueueItem(
        item: CaptureQueueItem,
        captureStartedAtMs: number | null,
    ): Promise<SubmitTextResult> {
        const activeEngine = ensureEngine();
        const durationMs =
            captureStartedAtMs !== null
                ? Math.max(0, Date.now() - captureStartedAtMs)
                : undefined;

        if (activeEngine !== null) {
            try {
                await ensureInitialized(activeEngine);
                await activeEngine.enqueue(item);
                sendMetric('local_saved', {
                    source_type: item.sourceType,
                    duration_ms: durationMs,
                });
                void activeEngine.flush();

                return { outcome: 'queued' };
            } catch {
                sendMetric('local_save_failed', {
                    source_type: item.sourceType,
                });
                // fall through to the direct send below
            }
        }

        try {
            await sendCapture(item);
            sendMetric('server_synced', {
                source_type: item.sourceType,
                retry_count: 0,
            });
            syncListeners.forEach((listener) => listener());

            return { outcome: 'sent_directly' };
        } catch {
            return {
                outcome: 'failed',
                message:
                    '保存できませんでした。端末保存も通信も失敗しています。内容を消さずに再試行してください。',
            };
        }
    }

    async function submitText(
        rawContent: string,
        captureStartedAtMs: number | null,
    ): Promise<SubmitTextResult> {
        const item = buildCaptureQueueItem({
            clientCaptureId: crypto.randomUUID(),
            sourceType: 'manual',
            rawContent,
            capturedAt: new Date().toISOString(),
        });

        return enqueueItem(item, captureStartedAtMs);
    }

    async function flush(): Promise<void> {
        await initAndFlush().catch(() => undefined);
    }

    async function discardRejected(clientCaptureId: string): Promise<boolean> {
        const activeEngine = ensureEngine();

        if (activeEngine === null) {
            return false;
        }

        await ensureInitialized(activeEngine);

        return activeEngine.discard(clientCaptureId);
    }

    function handleOnline(): void {
        void flush();
    }

    onMounted(() => {
        void flush();

        if (typeof window !== 'undefined' && !onlineListenerBound) {
            window.addEventListener('online', handleOnline);
            onlineListenerBound = true;
        }
    });

    onUnmounted(() => {
        localListeners.forEach((listener) => syncListeners.delete(listener));
        localListeners.length = 0;
    });

    return {
        pendingCaptures: queueItems,
        markCaptureStarted,
        submitText,
        enqueueItem,
        flush,
        discardRejected,
        onSynced: (listener) => {
            localListeners.push(listener);
            syncListeners.add(listener);
        },
    };
}
