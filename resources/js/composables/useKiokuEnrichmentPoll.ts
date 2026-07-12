import { isKiokuPendingStatus } from '@/lib/kiokuStatusPoll.mjs';
import type { KiokuMemory } from '@/types/kioku';

export function isKiokuMemoryPending(status: string): boolean {
    return isKiokuPendingStatus(status);
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
