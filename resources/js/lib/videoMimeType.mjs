/**
 * Resolve a MIME type browsers can omit for some containers (notably .mov).
 * Prefer a non-empty File.type, then fall back to the filename extension.
 *
 * @param {{ type?: string, name?: string }} file
 * @returns {string}
 */
export function resolveVideoMimeType(file) {
    const type = typeof file.type === 'string' ? file.type.trim() : '';

    if (type !== '' && type !== 'application/octet-stream') {
        return type;
    }

    const name = typeof file.name === 'string' ? file.name : '';
    const ext = name.includes('.')
        ? name.slice(name.lastIndexOf('.') + 1).toLowerCase()
        : '';

    const byExt = {
        mp4: 'video/mp4',
        m4v: 'video/mp4',
        webm: 'video/webm',
        mov: 'video/quicktime',
        qt: 'video/quicktime',
    };

    return byExt[ext] ?? type;
}

/**
 * @param {{ type?: string, name?: string } | null | undefined} file
 * @returns {boolean}
 */
export function isMovVideoFile(file) {
    if (file == null) {
        return false;
    }

    return resolveVideoMimeType(file) === 'video/quicktime';
}
