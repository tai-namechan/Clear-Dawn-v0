/**
 * Pure helpers shared by the native camera / barcode bridges.
 */

/** @type {Record<string, string>} */
const EXTENSIONS = {
    'image/jpeg': 'jpg',
    'image/png': 'png',
    'image/webp': 'webp',
    'image/heic': 'heic',
};

/**
 * File name for a photo taken with the native camera, e.g. "meal-20261123-081530.jpg".
 *
 * @param {string} mimeType
 * @param {Date} takenAt
 * @param {string} [prefix]
 * @returns {string}
 */
export function nativePhotoFileName(mimeType, takenAt, prefix = 'photo') {
    const pad = (value) => String(value).padStart(2, '0');
    const stamp = `${takenAt.getFullYear()}${pad(takenAt.getMonth() + 1)}${pad(takenAt.getDate())}-${pad(takenAt.getHours())}${pad(takenAt.getMinutes())}${pad(takenAt.getSeconds())}`;
    const extension = EXTENSIONS[String(mimeType).toLowerCase()] ?? 'jpg';

    return `${prefix}-${stamp}.${extension}`;
}

/**
 * The scanner can return surrounding whitespace or a trailing newline.
 * Returns null when nothing usable was read (e.g. the user cancelled).
 *
 * @param {string | null | undefined} raw
 * @returns {string | null}
 */
export function normalizeScannedCode(raw) {
    const code = String(raw ?? '').trim();

    return code === '' ? null : code;
}

/**
 * Plugin calls reject with a "cancelled" error when the user closes the
 * native camera / scanner; that is not a failure worth showing.
 *
 * @param {unknown} error
 * @returns {boolean}
 */
export function isUserCancellation(error) {
    const message = String(
        (error && typeof error === 'object' && 'message' in error
            ? error.message
            : error) ?? '',
    ).toLowerCase();

    return /cancel|canceled|cancelled|user denied|no image picked|キャンセル/.test(
        message,
    );
}
