declare module '@/lib/kiokuStatusPoll.mjs' {
    export const KIOKU_PENDING_STATUSES: Set<string>;
    export const KIOKU_TERMINAL_STATUSES: Set<string>;
    export const KIOKU_POLL_MAX_DURATION_MS: number;
    export const KIOKU_POLL_MAX_CONSECUTIVE_FAILURES: number;
    export const KIOKU_TIMEOUT_MESSAGE: string;

    export function isKiokuPendingStatus(status: string): boolean;
    export function isKiokuTerminalStatus(status: string): boolean;
    export function kiokuStatusPollIntervalMs(elapsedMs: number): number;
    export function shouldStopKiokuStatusPoll(elapsedMs: number): boolean;
    export function pendingMemoryIds(
        memories: ReadonlyArray<{ id: string; status: string }>,
    ): string[];
    export function areAllWatchedIdsTerminal(
        watchedIds: readonly string[],
        statuses: Readonly<Record<string, string>>,
        missingIds: readonly string[],
    ): boolean;
    export function samePendingIdSet(
        left: readonly string[],
        right: readonly string[],
    ): boolean;
    export function parseRetryAfterMs(
        value: unknown,
        now?: () => number,
    ): number | null;

    export type KiokuStatusPollResponse = {
        data: Record<string, string>;
        missing_ids: string[];
    };

    export type KiokuStatusPollEngineOptions = {
        now?: () => number;
        fetchStatus: (
            ids: string[],
            signal: AbortSignal,
        ) => Promise<KiokuStatusPollResponse>;
        onReload: () => void;
        onSchedule: (delayMs: number) => void;
        onClearSchedule: () => void;
        isDocumentHidden?: () => boolean;
        onTimedOutChange?: (timedOut: boolean) => void;
        onDevValidationError?: (body: unknown) => void;
    };

    export type KiokuStatusPollEngine = {
        start: (pendingIds: string[]) => void;
        setPendingIds: (pendingIds: string[]) => void;
        tick: () => Promise<void>;
        onHidden: () => void;
        onVisible: () => void;
        dispose: () => void;
        isInFlight: () => boolean;
        didReload: () => boolean;
        isTimedOut: () => boolean;
        isStopped: () => boolean;
        isDisposed: () => boolean;
        getConsecutiveFailures: () => number;
        getRunId: () => number;
    };

    export function createKiokuStatusPollEngine(
        options: KiokuStatusPollEngineOptions,
    ): KiokuStatusPollEngine;
}
