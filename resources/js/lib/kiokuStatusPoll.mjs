/**
 * Pure helpers + Vue-free poll engine for Kioku memory status polling.
 * Plain ESM so `node --test` runs without --experimental-strip-types (Node >=20).
 */

export const KIOKU_PENDING_STATUSES = new Set(['captured', 'enriching']);

export const KIOKU_TERMINAL_STATUSES = new Set([
    'ready',
    'failed',
    'missing',
]);

export const KIOKU_POLL_MAX_DURATION_MS = 180_000;
export const KIOKU_POLL_MAX_CONSECUTIVE_FAILURES = 5;

export const KIOKU_TIMEOUT_MESSAGE =
    'AI整理に時間がかかっています。あとで確認するか更新してください';

/**
 * @param {string} status
 */
export function isKiokuPendingStatus(status) {
    return KIOKU_PENDING_STATUSES.has(status);
}

/**
 * @param {string} status
 */
export function isKiokuTerminalStatus(status) {
    return KIOKU_TERMINAL_STATUSES.has(status);
}

/**
 * Backoff schedule:
 * 0–30s  → 3s
 * 30–90s → 5s
 * 90–180s → 8s
 *
 * @param {number} elapsedMs
 */
export function kiokuStatusPollIntervalMs(elapsedMs) {
    if (elapsedMs < 30_000) {
        return 3_000;
    }

    if (elapsedMs < 90_000) {
        return 5_000;
    }

    return 8_000;
}

/**
 * @param {number} elapsedMs
 */
export function shouldStopKiokuStatusPoll(elapsedMs) {
    return elapsedMs >= KIOKU_POLL_MAX_DURATION_MS;
}

/**
 * @param {ReadonlyArray<{ id: string, status: string }>} memories
 * @returns {string[]}
 */
export function pendingMemoryIds(memories) {
    return memories
        .filter((memory) => isKiokuPendingStatus(memory.status))
        .map((memory) => memory.id)
        .slice(0, 50);
}

/**
 * @param {readonly string[]} watchedIds
 * @param {Readonly<Record<string, string>>} statuses
 * @param {readonly string[]} missingIds
 */
export function areAllWatchedIdsTerminal(watchedIds, statuses, missingIds) {
    if (watchedIds.length === 0) {
        return true;
    }

    const missing = new Set(missingIds);

    return watchedIds.every((id) => {
        if (missing.has(id)) {
            return true;
        }

        const status = statuses[id];

        return status !== undefined && isKiokuTerminalStatus(status);
    });
}

/**
 * Order-independent ID set equality.
 *
 * @param {readonly string[]} left
 * @param {readonly string[]} right
 */
export function samePendingIdSet(left, right) {
    if (left.length !== right.length) {
        return false;
    }

    const rightSet = new Set(right);

    return left.every((id) => rightSet.has(id));
}

/**
 * @param {unknown} value
 * @param {() => number} now
 * @returns {number | null} milliseconds, or null when absent/unparseable
 */
export function parseRetryAfterMs(value, now = () => Date.now()) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    if (typeof value === 'number' && Number.isFinite(value) && value >= 0) {
        return Math.floor(value * 1000);
    }

    const text = String(value).trim();

    if (/^\d+$/.test(text)) {
        return Number.parseInt(text, 10) * 1000;
    }

    const dateMs = Date.parse(text);

    if (Number.isNaN(dateMs)) {
        return null;
    }

    return Math.max(0, dateMs - now());
}

/**
 * @param {unknown} error
 * @returns {{ status: number | null, retryAfterMs: number | null, body: unknown }}
 */
function readHttpError(error, now) {
    if (!error || typeof error !== 'object') {
        return { status: null, retryAfterMs: null, body: undefined };
    }

    const status =
        'status' in error && typeof error.status === 'number'
            ? error.status
            : null;

    let retryAfterMs = null;

    if ('retryAfterSeconds' in error && error.retryAfterSeconds != null) {
        retryAfterMs = parseRetryAfterMs(error.retryAfterSeconds, now);
    } else if ('retryAfterMs' in error && error.retryAfterMs != null) {
        const raw = error.retryAfterMs;
        retryAfterMs =
            typeof raw === 'number' && Number.isFinite(raw)
                ? Math.max(0, Math.floor(raw))
                : null;
    } else if ('retryAfter' in error) {
        retryAfterMs = parseRetryAfterMs(error.retryAfter, now);
    }

    const body = 'body' in error ? error.body : undefined;

    return { status, retryAfterMs, body };
}

/**
 * @typedef {object} KiokuStatusPollResponse
 * @property {Record<string, string>} data
 * @property {string[]} missing_ids
 */

/**
 * @typedef {object} KiokuStatusPollEngineOptions
 * @property {() => number} [now]
 * @property {(ids: string[], signal: AbortSignal) => Promise<KiokuStatusPollResponse>} fetchStatus
 * @property {() => void} onReload
 * @property {(delayMs: number) => void} onSchedule
 * @property {() => void} onClearSchedule
 * @property {() => boolean} [isDocumentHidden]
 * @property {(timedOut: boolean) => void} [onTimedOutChange]
 * @property {(body: unknown) => void} [onDevValidationError]
 */

/**
 * @param {KiokuStatusPollEngineOptions} options
 */
export function createKiokuStatusPollEngine(options) {
    const now = options.now ?? (() => Date.now());
    const isHidden = options.isDocumentHidden ?? (() => false);

    /** @type {string[]} */
    let pendingIds = [];
    /** @type {number | null} */
    let startedAt = null;
    let inFlight = false;
    let stopped = false;
    let disposed = false;
    let reloaded = false;
    let timedOut = false;
    let consecutiveFailures = 0;
    /** Monotonic run generation — stale await/catch/finally must no-op when mismatched. */
    let runId = 0;
    /** @type {AbortController | null} */
    let abortController = null;
    /** @type {string[]} */
    let watchedIds = [];

    /**
     * @param {boolean} value
     */
    function setTimedOut(value) {
        if (timedOut === value) {
            return;
        }

        timedOut = value;
        options.onTimedOutChange?.(value);
    }

    function clearSchedule() {
        options.onClearSchedule();
    }

    /**
     * Invalidate the current run so any in-flight await/catch/finally becomes a no-op.
     */
    function bumpRun() {
        runId += 1;
    }

    function abortRequest() {
        if (abortController !== null) {
            abortController.abort();
            abortController = null;
        }
    }

    /**
     * @param {number} requestRunId
     * @param {AbortController} controller
     */
    function isCurrentRequest(requestRunId, controller) {
        return (
            !disposed &&
            requestRunId === runId &&
            abortController === controller
        );
    }

    /**
     * @param {boolean} [markTimedOut]
     */
    function stop(markTimedOut = false) {
        clearSchedule();
        abortRequest();
        startedAt = null;
        inFlight = false;
        stopped = true;

        if (markTimedOut) {
            setTimedOut(true);
        }
    }

    /**
     * @param {number} waitMs
     * @param {number} requestRunId
     */
    function scheduleAfter(waitMs, requestRunId) {
        if (requestRunId !== runId || disposed || stopped) {
            return;
        }

        clearSchedule();

        if (startedAt === null || pendingIds.length === 0) {
            return;
        }

        if (isHidden()) {
            return;
        }

        const elapsed = now() - startedAt;
        const remaining = KIOKU_POLL_MAX_DURATION_MS - elapsed;

        if (remaining <= 0) {
            stop(true);

            return;
        }

        if (waitMs >= remaining) {
            stop(true);

            return;
        }

        options.onSchedule(waitMs);
    }

    /**
     * @param {number} requestRunId
     */
    function scheduleNext(requestRunId) {
        if (requestRunId !== runId || disposed || stopped || startedAt === null) {
            return;
        }

        const elapsed = now() - startedAt;
        scheduleAfter(kiokuStatusPollIntervalMs(elapsed), requestRunId);
    }

    /**
     * @param {number} requestRunId
     */
    function reloadOnce(requestRunId) {
        if (requestRunId !== runId || reloaded || disposed) {
            return;
        }

        reloaded = true;
        bumpRun();
        stop(false);
        options.onReload();
    }

    async function tick() {
        if (disposed || stopped || inFlight) {
            return;
        }

        if (isHidden()) {
            return;
        }

        if (pendingIds.length === 0) {
            stop(false);

            return;
        }

        if (startedAt === null) {
            startedAt = now();
        }

        const elapsed = now() - startedAt;

        if (shouldStopKiokuStatusPoll(elapsed)) {
            stop(true);

            return;
        }

        const requestRunId = runId;
        watchedIds = [...pendingIds];
        inFlight = true;
        abortRequest();
        const controller = new AbortController();
        abortController = controller;
        const signal = controller.signal;

        try {
            const response = await options.fetchStatus(watchedIds, signal);

            if (!isCurrentRequest(requestRunId, controller)) {
                return;
            }

            consecutiveFailures = 0;

            if (
                areAllWatchedIdsTerminal(
                    watchedIds,
                    response.data,
                    response.missing_ids,
                )
            ) {
                reloadOnce(requestRunId);

                return;
            }

            scheduleNext(requestRunId);
        } catch (error) {
            if (!isCurrentRequest(requestRunId, controller)) {
                return;
            }

            if (error instanceof DOMException && error.name === 'AbortError') {
                return;
            }

            const { status, retryAfterMs, body } = readHttpError(error, now);

            if (status !== null && [401, 419, 422].includes(status)) {
                if (status === 422) {
                    options.onDevValidationError?.(body);
                }

                stop(false);

                return;
            }

            if (status === 429) {
                const currentElapsed =
                    startedAt !== null ? now() - startedAt : 0;
                const backoffMs = kiokuStatusPollIntervalMs(currentElapsed);
                const waitMs =
                    retryAfterMs !== null
                        ? Math.max(retryAfterMs, backoffMs)
                        : backoffMs;

                scheduleAfter(waitMs, requestRunId);

                return;
            }

            consecutiveFailures += 1;

            if (consecutiveFailures >= KIOKU_POLL_MAX_CONSECUTIVE_FAILURES) {
                stop(true);

                return;
            }

            scheduleNext(requestRunId);
        } finally {
            // Stale generations must not clear the current request's inFlight /
            // AbortController — a newer B may already own them.
            if (requestRunId !== runId) {
                return;
            }

            inFlight = false;

            if (abortController === controller) {
                abortController = null;
            }
        }
    }

    /**
     * @param {string[]} ids
     */
    function begin(ids) {
        if (disposed) {
            return;
        }

        bumpRun();
        pendingIds = [...ids];
        stopped = false;
        reloaded = false;
        setTimedOut(false);
        consecutiveFailures = 0;
        abortRequest();
        inFlight = false;
        clearSchedule();

        if (pendingIds.length === 0) {
            startedAt = null;
            stopped = true;

            return;
        }

        startedAt = now();

        if (!isHidden()) {
            void tick();
        }
    }

    return {
        /**
         * @param {string[]} ids
         */
        start: begin,
        /**
         * @param {string[]} ids
         */
        setPendingIds(ids) {
            if (disposed) {
                return;
            }

            const next = [...ids];

            if (next.length === 0) {
                bumpRun();
                pendingIds = [];
                stop(false);

                return;
            }

            if (samePendingIdSet(next, pendingIds)) {
                pendingIds = next;

                return;
            }

            begin(next);
        },
        tick,
        onHidden() {
            clearSchedule();
        },
        onVisible() {
            if (disposed || stopped || pendingIds.length === 0) {
                return;
            }

            void tick();
        },
        dispose() {
            disposed = true;
            bumpRun();
            stop(false);
        },
        isInFlight: () => inFlight,
        didReload: () => reloaded,
        isTimedOut: () => timedOut,
        isStopped: () => stopped,
        isDisposed: () => disposed,
        getConsecutiveFailures: () => consecutiveFailures,
        /** @internal test aid */
        getRunId: () => runId,
    };
}
