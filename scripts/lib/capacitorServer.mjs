/**
 * Validates the server the iOS app loads (CAP_SERVER_URL).
 * Production must be https; plain http is only allowed for local
 * development hosts (and then needs an ATS exception in Xcode).
 *
 * @param {string | undefined} raw
 * @returns {{ url: string, cleartext: boolean } | null}
 */
export function resolveCapacitorServerUrl(raw) {
    const value = String(raw ?? '').trim();

    if (value === '') {
        return null;
    }

    let parsed;

    try {
        parsed = new URL(value);
    } catch {
        throw new Error(`CAP_SERVER_URL is not a valid URL: ${value}`);
    }

    const localHosts = ['localhost', '127.0.0.1'];
    const isLocal =
        localHosts.includes(parsed.hostname) ||
        /^192\.168\.\d+\.\d+$/.test(parsed.hostname);

    if (parsed.protocol === 'https:') {
        return { url: parsed.origin, cleartext: false };
    }

    if (parsed.protocol === 'http:' && isLocal) {
        return { url: parsed.origin, cleartext: true };
    }

    throw new Error(
        'CAP_SERVER_URL must use https (http is only for local development).',
    );
}

/**
 * Contents of capacitor/www/app-config.js, read by the bundled pages.
 *
 * @param {string | null} url
 * @returns {string}
 */
export function appConfigScript(url) {
    return `window.CD_SERVER_URL = ${JSON.stringify(url)};\n`;
}
