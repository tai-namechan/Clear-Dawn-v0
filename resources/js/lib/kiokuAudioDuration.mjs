/**
 * Client-declared voice duration helpers.
 * MediaRecorder WebM/MP4 often omits container duration; native <audio>
 * then grows "max" time while playing. Prefer memory_assets.duration_ms.
 */

/**
 * @param {number | null | undefined} durationMs
 * @returns {number | null} seconds, or null when unknown
 */
export function kiokuAudioDurationSeconds(durationMs) {
    if (durationMs == null || !Number.isFinite(durationMs) || durationMs <= 0) {
        return null;
    }

    return durationMs / 1000;
}

/**
 * @param {number | null | undefined} seconds
 * @returns {string} m:ss
 */
export function formatKiokuAudioClock(seconds) {
    if (seconds == null || !Number.isFinite(seconds) || seconds < 0) {
        return '0:00';
    }

    const total = Math.floor(seconds);
    const minutes = Math.floor(total / 60);
    const secs = total % 60;

    return `${minutes}:${String(secs).padStart(2, '0')}`;
}
