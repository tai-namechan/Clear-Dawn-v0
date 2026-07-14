/**
 * Pure helpers for voice capture recording (docs/product/kioku-quick-capture.md §11).
 * Browser-API-free so `node --test` covers the decisions: MIME selection,
 * the 3-minute auto-stop, and upload filename mapping.
 */

export const KIOKU_MAX_RECORDING_MS = 180_000;

/**
 * Preference order: Safari records audio/mp4, Chrome audio/webm;codecs=opus.
 * Never assume a fixed container — always feature-detect.
 */
export const PREFERRED_AUDIO_MIME_TYPES = [
    'audio/mp4',
    'audio/webm;codecs=opus',
    'audio/webm',
    'audio/ogg;codecs=opus',
    'audio/ogg',
];

/**
 * @param {(mimeType: string) => boolean} isTypeSupported
 * @returns {string | null} null → construct MediaRecorder without mimeType
 */
export function pickSupportedAudioMimeType(isTypeSupported) {
    for (const mimeType of PREFERRED_AUDIO_MIME_TYPES) {
        try {
            if (isTypeSupported(mimeType)) {
                return mimeType;
            }
        } catch {
            // Some engines throw on unknown types; keep probing.
        }
    }

    return null;
}

/**
 * @param {number} elapsedMs
 * @param {number} [maxMs]
 */
export function shouldAutoStopRecording(
    elapsedMs,
    maxMs = KIOKU_MAX_RECORDING_MS,
) {
    return elapsedMs >= maxMs;
}

/**
 * @param {number} elapsedMs
 * @returns {string} "M:SS"
 */
export function formatRecordingElapsed(elapsedMs) {
    const totalSeconds = Math.max(0, Math.floor(elapsedMs / 1000));
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    return `${minutes}:${String(seconds).padStart(2, '0')}`;
}

/**
 * Upload filename extension for a recorded MIME type. The server re-detects
 * the real MIME from content; this only names the multipart part.
 *
 * @param {string | null | undefined} mimeType
 */
export function fileExtensionForAudioMime(mimeType) {
    const base = (mimeType ?? '').split(';')[0].trim().toLowerCase();

    switch (base) {
        case 'audio/webm':
        case 'video/webm':
            return 'webm';
        case 'audio/mp4':
        case 'video/mp4':
            return 'm4a';
        case 'audio/ogg':
        case 'application/ogg':
            return 'ogg';
        case 'audio/mpeg':
        case 'audio/mp3':
            return 'mp3';
        case 'audio/wav':
        case 'audio/x-wav':
        case 'audio/vnd.wave':
            return 'wav';
        default:
            return 'bin';
    }
}
