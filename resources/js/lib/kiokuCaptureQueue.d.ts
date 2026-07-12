declare module '@/lib/kiokuCaptureQueue.mjs' {
    export type CaptureQueueItem = {
        clientCaptureId: string;
        sourceType: 'manual' | 'voice';
        rawContent: string | null;
        audioBlob: Blob | null;
        audioMimeType: string | null;
        durationMs: number | null;
        capturedAt: string;
        enqueuedAtMs: number;
        retryCount: number;
        lastError: string | null;
        rejected: boolean;
    };

    export type CaptureQueueStorage = {
        all: () => Promise<CaptureQueueItem[]>;
        put: (item: CaptureQueueItem) => Promise<void>;
        remove: (clientCaptureId: string) => Promise<void>;
    };

    export type CaptureSendResult = {
        memoryId: string;
        created: boolean;
    };

    export type CaptureQueueEngineOptions = {
        storage: CaptureQueueStorage;
        sendCapture: (item: CaptureQueueItem) => Promise<CaptureSendResult>;
        onChange?: (items: CaptureQueueItem[]) => void;
        onItemSynced?: (
            item: CaptureQueueItem,
            result: CaptureSendResult,
        ) => void;
        onItemSyncFailed?: (
            item: CaptureQueueItem,
            error: unknown,
            willRetry: boolean,
        ) => void;
    };

    export type CaptureQueueEngine = {
        init: () => Promise<void>;
        enqueue: (item: CaptureQueueItem) => Promise<void>;
        flush: () => Promise<{
            synced: number;
            failed: number;
            skipped: boolean;
        }>;
        discard: (clientCaptureId: string) => Promise<boolean>;
        getItems: () => CaptureQueueItem[];
        isFlushing: () => boolean;
        isInitialized: () => boolean;
    };

    export function buildCaptureQueueItem(
        input: {
            clientCaptureId: string;
            sourceType: 'manual' | 'voice';
            rawContent?: string | null;
            audioBlob?: Blob | null;
            audioMimeType?: string | null;
            durationMs?: number | null;
            capturedAt: string;
        },
        now?: () => number,
    ): CaptureQueueItem;

    export function isPermanentCaptureRejection(status: number | null): boolean;

    export function isAuthFailure(status: number | null): boolean;

    export function createCaptureQueueEngine(
        options: CaptureQueueEngineOptions,
    ): CaptureQueueEngine;
}
