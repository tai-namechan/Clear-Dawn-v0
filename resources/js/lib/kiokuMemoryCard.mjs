/**
 * Pure helpers for Kioku MemoryCard navigation vs enrichment chrome.
 * Voice memories keep a durable audio original as canonical raw, so the
 * card must stay openable while transcription/enrichment is still pending
 * (including KIOKU_TRANSCRIPTION_PROVIDER=none).
 */

import { isKiokuPendingStatus } from './kiokuStatusPoll.mjs';

/**
 * Whether the card should navigate to Detail.
 * Independent of the enrichment spinner: voice is always navigable once
 * the Memory exists server-side.
 *
 * @param {{ source_type: string, status: string }} memory
 */
export function isKiokuMemoryCardNavigable(memory) {
    if (memory.source_type === 'voice') {
        return true;
    }

    return !isKiokuPendingStatus(memory.status);
}

/**
 * Whether to show the enrichment/pending chrome (spinner).
 * Voice stays out of this chrome until a transcript is ready (or status is
 * already enriching) so provider=none never looks permanently "整理中".
 *
 * @param {{
 *   source_type: string,
 *   status: string,
 *   transcription_status?: string | null,
 * }} memory
 * @param {{ transcriptionEnabled?: boolean }} [options]
 */
export function isKiokuMemoryCardEnriching(
    memory,
    { transcriptionEnabled = true } = {},
) {
    if (memory.source_type === 'voice') {
        if (memory.status === 'enriching') {
            return true;
        }

        // Transcript ready, still waiting for EnrichMemoryJob.
        if (
            transcriptionEnabled &&
            memory.transcription_status === 'ready' &&
            isKiokuPendingStatus(memory.status)
        ) {
            return true;
        }

        return false;
    }

    return isKiokuPendingStatus(memory.status);
}

/**
 * Avoid showing the placeholder "整理中…" for voice that has not reached
 * enrichment yet (provider=none pending, or transcription still running).
 *
 * @param {{
 *   source_type: string,
 *   title: string,
 *   transcription_status?: string | null,
 * }} memory
 */
export function kiokuMemoryDisplayTitle(memory) {
    if (
        memory.source_type === 'voice' &&
        memory.title === '整理中…' &&
        memory.transcription_status !== 'ready'
    ) {
        return '音声メモ';
    }

    return memory.title;
}

/**
 * List/detail failure chrome: distinguish transcription failure from
 * enrichment failure so voice MIME/provider errors are not labeled as AI整理.
 *
 * @param {{
 *   source_type: string,
 *   status: string,
 *   transcription_status?: string | null,
 * }} memory
 */
export function kiokuMemoryFailureLabel(memory) {
    if (
        memory.source_type === 'voice' &&
        memory.transcription_status === 'failed'
    ) {
        return '文字起こしに失敗しました';
    }

    return 'AI整理に失敗しました';
}

/**
 * "AIで再整理" needs a transcript (or non-voice raw content). Voice without
 * transcription_status=ready has nothing EnrichMemoryJob can classify.
 *
 * @param {{
 *   source_type: string,
 *   status: string,
 *   transcription_status?: string | null,
 * }} memory
 */
export function canKiokuMemoryReenrich(memory) {
    if (memory.status !== 'ready' && memory.status !== 'failed') {
        return false;
    }

    if (memory.source_type === 'voice') {
        return memory.transcription_status === 'ready';
    }

    return true;
}
