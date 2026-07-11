import { router } from '@inertiajs/vue3';
import { computed, toValue  } from 'vue';
import type {MaybeRefOrGetter} from 'vue';
import { useAsyncPoll } from '@/composables/useAsyncPoll';
import type { KiokuMemory } from '@/types/kioku';

const PENDING_STATUSES = new Set(['captured', 'enriching']);

export function isKiokuMemoryPending(status: string): boolean {
    return PENDING_STATUSES.has(status);
}

export function kiokuEnrichmentLabel(
    memory: Pick<KiokuMemory, 'status' | 'captured_at'>,
    nowMs: number = Date.now(),
): string {
    if (memory.status === 'failed') {
        return 'AI整理に失敗しました';
    }

    if (memory.status === 'ready') {
        return '✨ 整理が完了しました';
    }

    if (memory.status === 'captured') {
        return '○ 保存しました';
    }

    const capturedAt =
        memory.captured_at !== null ? Date.parse(memory.captured_at) : Number.NaN;
    const elapsed = Number.isFinite(capturedAt) ? nowMs - capturedAt : 0;

    if (elapsed < 4000) {
        return '🧠 AIが内容を分析しています…';
    }

    return '🏷️ タグを付けています…';
}

/**
 * Polls Kioku index props while any memory is captured/enriching.
 */
export function useKiokuEnrichmentPoll(
    memories: MaybeRefOrGetter<KiokuMemory[]>,
): void {
    const hasPending = computed(() =>
        toValue(memories).some((memory) => isKiokuMemoryPending(memory.status)),
    );

    useAsyncPoll({
        enabled: hasPending,
        intervalMs: 3000,
        maxDurationMs: 5 * 60 * 1000,
        tick: () =>
            new Promise<void>((resolve) => {
                router.reload({
                    only: ['memories', 'typeCounts', 'sourceCounts', 'totalCount'],
                    preserveUrl: true,
                    onFinish: () => resolve(),
                });
            }),
    });
}
