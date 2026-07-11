import { router } from '@inertiajs/vue3';
import {
    computed,
    onMounted,
    onUnmounted,
    ref,
    toValue,
    watch,
} from 'vue';
import type { MaybeRefOrGetter, Ref } from 'vue';
import { apiFetch } from '@/lib/apiFetch';
import {
    createKiokuStatusPollEngine,
    KIOKU_TIMEOUT_MESSAGE,
    pendingMemoryIds,
} from '@/lib/kiokuStatusPoll.mjs';
import type { KiokuStatusPollResponse } from '@/lib/kiokuStatusPoll.mjs';
import { status as memoryStatus } from '@/routes/kioku/memories';
import type { KiokuMemory } from '@/types/kioku';

export type UseKiokuStatusPollReturn = {
    timedOut: Ref<boolean>;
    timeoutMessage: string;
};

const RELOAD_ONLY = [
    'memories',
    'typeCounts',
    'sourceCounts',
    'totalCount',
] as const;

/**
 * Polls GET /kioku/memories/status for captured/enriching IDs, then reloads
 * Inertia props once when every watched ID is ready/failed/missing.
 */
export function useKiokuStatusPoll(
    memories: MaybeRefOrGetter<
        ReadonlyArray<Pick<KiokuMemory, 'id' | 'status'>>
    >,
): UseKiokuStatusPollReturn {
    const timedOut = ref(false);
    const pendingIds = computed(() => pendingMemoryIds(toValue(memories)));

    let timer: ReturnType<typeof setTimeout> | null = null;

    const engine = createKiokuStatusPollEngine({
        isDocumentHidden: () =>
            typeof document !== 'undefined' && document.hidden,
        onTimedOutChange: (value) => {
            timedOut.value = value;
        },
        onClearSchedule: () => {
            if (timer !== null) {
                clearTimeout(timer);
                timer = null;
            }
        },
        onSchedule: (delayMs) => {
            if (timer !== null) {
                clearTimeout(timer);
            }

            timer = setTimeout(() => {
                void engine.tick();
            }, delayMs);
        },
        onReload: () => {
            router.reload({
                only: [...RELOAD_ONLY],
                preserveUrl: true,
            });
        },
        fetchStatus: (ids, signal) =>
            apiFetch<KiokuStatusPollResponse>(
                memoryStatus.url({
                    query: {
                        ids,
                    },
                }),
                {
                    method: 'GET',
                    signal,
                },
            ),
        onDevValidationError: (body) => {
            if (import.meta.env.DEV) {
                console.error('[useKiokuStatusPoll] validation failed', body);
            }
        },
    });

    function onVisibility(): void {
        if (typeof document === 'undefined') {
            return;
        }

        if (document.visibilityState === 'hidden') {
            engine.onHidden();
        } else {
            engine.onVisible();
        }
    }

    onMounted(() => {
        engine.start(pendingIds.value);

        if (typeof document !== 'undefined') {
            document.addEventListener('visibilitychange', onVisibility);
        }
    });

    onUnmounted(() => {
        engine.dispose();

        if (typeof document !== 'undefined') {
            document.removeEventListener('visibilitychange', onVisibility);
        }
    });

    watch(pendingIds, (ids) => {
        engine.setPendingIds(ids);
    });

    return {
        timedOut,
        timeoutMessage: KIOKU_TIMEOUT_MESSAGE,
    };
}
