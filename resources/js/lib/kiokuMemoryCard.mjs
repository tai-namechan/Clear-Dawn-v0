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
 * Whether to show the enrichment/pending chrome (spinner, non-ready label).
 * Does not control clickability — see isKiokuMemoryCardNavigable.
 *
 * @param {string} status
 */
export function isKiokuMemoryCardEnriching(status) {
    return isKiokuPendingStatus(status);
}
