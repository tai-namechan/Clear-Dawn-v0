import { router } from '@inertiajs/vue3';
import { computed, toValue  } from 'vue';
import type {MaybeRefOrGetter} from 'vue';
import { useAsyncPoll } from '@/composables/useAsyncPoll';

const PENDING_STATUSES = new Set(['pending', 'generating']);

export type YoyuBriefingStatus =
    | 'pending'
    | 'generating'
    | 'ready'
    | 'failed'
    | null;

export function isYoyuBriefingPending(status: YoyuBriefingStatus): boolean {
    return status !== null && PENDING_STATUSES.has(status);
}

export function yoyuBriefingLabel(
    status: YoyuBriefingStatus,
    startedAtMs: number | null = null,
    nowMs: number = Date.now(),
): string {
    if (status === 'failed') {
        return 'AI整理に失敗しました';
    }

    if (status === 'ready') {
        return '✨ 整理が完了しました';
    }

    if (status === 'pending') {
        return '○ 保存しました';
    }

    const elapsed =
        startedAtMs !== null ? Math.max(0, nowMs - startedAtMs) : 0;

    if (elapsed < 4000) {
        return '🧠 AIが内容を分析しています…';
    }

    return '🏷️ タグを付けています…';
}

/**
 * Polls Yoyu home briefing props while status is pending/generating.
 */
export function useYoyuBriefingPoll(
    briefingStatus: MaybeRefOrGetter<YoyuBriefingStatus>,
): void {
    const hasPending = computed(() =>
        isYoyuBriefingPending(toValue(briefingStatus)),
    );

    useAsyncPoll({
        enabled: hasPending,
        intervalMs: 3000,
        maxDurationMs: 5 * 60 * 1000,
        tick: () =>
            new Promise<void>((resolve) => {
                router.reload({
                    only: ['briefing', 'briefingStatus', 'structuredBriefing'],
                    preserveUrl: true,
                    onFinish: () => resolve(),
                });
            }),
    });
}
