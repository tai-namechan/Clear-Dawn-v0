/**
 * Pure engine for the Kioku capture queue (docs/product/kioku-quick-capture.md §7).
 * Storage is injected (IndexedDB in the browser, in-memory in node --test) so
 * the durability contract stays testable: an item leaves the queue only after
 * the server confirms the capture.
 */

/**
 * @typedef {object} CaptureQueueItem
 * @property {string} clientCaptureId
 * @property {'manual' | 'voice'} sourceType
 * @property {string | null} rawContent  manual only
 * @property {Blob | null} audioBlob  voice only
 * @property {string | null} audioMimeType
 * @property {number | null} durationMs  voice recording length
 * @property {string} capturedAt  ISO-8601
 * @property {number} enqueuedAtMs
 * @property {number} retryCount
 * @property {string | null} lastError
 * @property {boolean} rejected  server said 422 — kept for the user, not retried
 */

/**
 * @typedef {object} CaptureQueueStorage
 * @property {() => Promise<CaptureQueueItem[]>} all
 * @property {(item: CaptureQueueItem) => Promise<void>} put
 * @property {(clientCaptureId: string) => Promise<void>} remove
 */

/**
 * @typedef {object} CaptureSendResult
 * @property {string} memoryId
 * @property {boolean} created
 */

/**
 * @param {{
 *   clientCaptureId: string,
 *   sourceType: 'manual' | 'voice',
 *   rawContent?: string | null,
 *   audioBlob?: Blob | null,
 *   audioMimeType?: string | null,
 *   durationMs?: number | null,
 *   capturedAt: string,
 * }} input
 * @param {() => number} [now]
 * @returns {CaptureQueueItem}
 */
export function buildCaptureQueueItem(input, now = () => Date.now()) {
    return {
        clientCaptureId: input.clientCaptureId,
        sourceType: input.sourceType,
        rawContent: input.rawContent ?? null,
        audioBlob: input.audioBlob ?? null,
        audioMimeType: input.audioMimeType ?? null,
        durationMs: input.durationMs ?? null,
        capturedAt: input.capturedAt,
        enqueuedAtMs: now(),
        retryCount: 0,
        lastError: null,
        rejected: false,
    };
}

/**
 * HTTP statuses that mean "retrying the identical payload cannot succeed".
 *
 * @param {number | null} status
 */
export function isPermanentCaptureRejection(status) {
    return status === 422 || status === 413;
}

/**
 * Statuses that mean the session is gone — stop the whole flush and keep
 * every item for after re-login.
 *
 * @param {number | null} status
 */
export function isAuthFailure(status) {
    return status === 401 || status === 419;
}

/**
 * @typedef {object} CaptureQueueEngineOptions
 * @property {CaptureQueueStorage} storage
 * @property {(item: CaptureQueueItem) => Promise<CaptureSendResult>} sendCapture
 * @property {(items: CaptureQueueItem[]) => void} [onChange]
 * @property {(item: CaptureQueueItem, result: CaptureSendResult) => void} [onItemSynced]
 * @property {(item: CaptureQueueItem, error: unknown, willRetry: boolean) => void} [onItemSyncFailed]
 */

/**
 * @param {CaptureQueueEngineOptions} options
 */
export function createCaptureQueueEngine(options) {
    /** @type {CaptureQueueItem[]} */
    let items = [];
    let initialized = false;
    let flushing = false;

    function emitChange() {
        options.onChange?.(items.map((item) => ({ ...item })));
    }

    /**
     * @param {unknown} error
     * @returns {number | null}
     */
    function errorStatus(error) {
        if (
            error &&
            typeof error === 'object' &&
            'status' in error &&
            typeof error.status === 'number'
        ) {
            return error.status;
        }

        return null;
    }

    return {
        /**
         * Load persisted items (survivors of a reload / browser restart).
         */
        async init() {
            if (initialized) {
                return;
            }

            items = await options.storage.all();
            initialized = true;
            emitChange();
        },

        /**
         * Persist first, then expose. Throws when storage fails — callers
         * must NOT report "saved" in that case.
         *
         * @param {CaptureQueueItem} item
         */
        async enqueue(item) {
            await options.storage.put(item);
            items = [
                ...items.filter(
                    (existing) =>
                        existing.clientCaptureId !== item.clientCaptureId,
                ),
                item,
            ];
            emitChange();
        },

        /**
         * Send every pending item once, sequentially. An item is removed
         * only after the server acknowledged it; failures keep the item
         * (with retryCount bumped) for the next flush.
         *
         * @returns {Promise<{ synced: number, failed: number, skipped: boolean }>}
         */
        async flush() {
            if (flushing || items.length === 0) {
                return { synced: 0, failed: 0, skipped: true };
            }

            flushing = true;
            let synced = 0;
            let failed = 0;

            try {
                for (const item of [...items]) {
                    if (item.rejected) {
                        continue;
                    }

                    try {
                        const result = await options.sendCapture(item);

                        await options.storage.remove(item.clientCaptureId);
                        items = items.filter(
                            (existing) =>
                                existing.clientCaptureId !==
                                item.clientCaptureId,
                        );
                        synced += 1;
                        emitChange();
                        options.onItemSynced?.(item, result);
                    } catch (error) {
                        const status = errorStatus(error);

                        if (isAuthFailure(status)) {
                            failed += 1;
                            options.onItemSyncFailed?.(item, error, true);
                            break;
                        }

                        const updated = {
                            ...item,
                            retryCount: item.retryCount + 1,
                            lastError:
                                status !== null
                                    ? `http_${status}`
                                    : 'network_error',
                            rejected: isPermanentCaptureRejection(status),
                        };

                        try {
                            await options.storage.put(updated);
                        } catch {
                            // Keep the in-memory copy even if persisting the
                            // retry metadata fails — never drop the raw.
                        }

                        items = items.map((existing) =>
                            existing.clientCaptureId === item.clientCaptureId
                                ? updated
                                : existing,
                        );
                        failed += 1;
                        emitChange();
                        options.onItemSyncFailed?.(
                            item,
                            error,
                            !updated.rejected,
                        );
                    }
                }
            } finally {
                flushing = false;
            }

            return { synced, failed, skipped: false };
        },

        getItems: () => items.map((item) => ({ ...item })),
        isFlushing: () => flushing,
        isInitialized: () => initialized,
    };
}
