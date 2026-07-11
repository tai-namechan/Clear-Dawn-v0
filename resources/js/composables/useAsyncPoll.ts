import {
    onMounted,
    onUnmounted,
    toValue,
    watch
    
} from 'vue';
import type {MaybeRefOrGetter} from 'vue';

export type UseAsyncPollOptions = {
    /** When true, polling runs (subject to visibility / in-flight / max duration). */
    enabled: MaybeRefOrGetter<boolean>;
    /** Poll interval in ms. Default 3000. */
    intervalMs?: number;
    /** Hard stop after this many ms. Default 5 minutes. */
    maxDurationMs?: number;
    /** Called on each tick. Must not overlap — in-flight ticks are skipped. */
    tick: () => void | Promise<void>;
};

/**
 * Lightweight async status poller (MVP stand-in for Echo/Reverb).
 * - Runs only while `enabled` is true
 * - Pauses while the document is hidden
 * - Skips ticks while a previous tick is in flight
 * - Stops after maxDurationMs
 * - Clears interval on unmount
 */
export function useAsyncPoll(options: UseAsyncPollOptions): {
    isPolling: () => boolean;
    stop: () => void;
} {
    const intervalMs = options.intervalMs ?? 3000;
    const maxDurationMs = options.maxDurationMs ?? 5 * 60 * 1000;

    let timer: ReturnType<typeof setInterval> | null = null;
    let startedAt: number | null = null;
    let inFlight = false;
    let stopped = false;

    function isPolling(): boolean {
        return timer !== null;
    }

    function stop(): void {
        if (timer !== null) {
            clearInterval(timer);
            timer = null;
        }

        startedAt = null;
        inFlight = false;
    }

    async function runTick(): Promise<void> {
        if (stopped || inFlight) {
            return;
        }

        if (typeof document !== 'undefined' && document.visibilityState === 'hidden') {
            return;
        }

        if (!toValue(options.enabled)) {
            stop();

            return;
        }

        if (startedAt !== null && Date.now() - startedAt >= maxDurationMs) {
            stop();

            return;
        }

        inFlight = true;

        try {
            await options.tick();
        } finally {
            inFlight = false;
        }

        if (!toValue(options.enabled)) {
            stop();
        }
    }

    function start(): void {
        if (stopped || timer !== null) {
            return;
        }

        if (!toValue(options.enabled)) {
            return;
        }

        startedAt = Date.now();
        void runTick();
        timer = setInterval(() => {
            void runTick();
        }, intervalMs);
    }

    function sync(): void {
        if (toValue(options.enabled)) {
            start();
        } else {
            stop();
        }
    }

    function onVisibility(): void {
        if (typeof document === 'undefined') {
            return;
        }

        if (document.visibilityState === 'visible') {
            sync();
        }
    }

    onMounted(() => {
        sync();

        if (typeof document !== 'undefined') {
            document.addEventListener('visibilitychange', onVisibility);
        }
    });

    onUnmounted(() => {
        stopped = true;
        stop();

        if (typeof document !== 'undefined') {
            document.removeEventListener('visibilitychange', onVisibility);
        }
    });

    watch(
        () => toValue(options.enabled),
        () => {
            sync();
        },
    );

    return { isPolling, stop };
}
