export class ApiError extends Error {
    constructor(
        public readonly status: number,
        public readonly body: unknown,
        public readonly retryAfterSeconds: number | null = null,
    ) {
        super(`API request failed with status ${status}`);
        this.name = 'ApiError';
    }
}

function getXsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

function parseRetryAfterSeconds(header: string | null): number | null {
    if (header === null || header.trim() === '') {
        return null;
    }

    const trimmed = header.trim();

    if (/^\d+$/.test(trimmed)) {
        return Number.parseInt(trimmed, 10);
    }

    const dateMs = Date.parse(trimmed);

    if (Number.isNaN(dateMs)) {
        return null;
    }

    return Math.max(0, Math.ceil((dateMs - Date.now()) / 1000));
}

export async function apiFetch<T>(
    url: string,
    options: RequestInit = {},
): Promise<T> {
    const headers = new Headers(options.headers);

    if (!headers.has('Accept')) {
        headers.set('Accept', 'application/json');
    }

    if (!headers.has('X-Requested-With')) {
        headers.set('X-Requested-With', 'XMLHttpRequest');
    }

    const csrfToken = getXsrfToken();

    if (csrfToken && !headers.has('X-XSRF-TOKEN')) {
        headers.set('X-XSRF-TOKEN', csrfToken);
    }

    if (
        options.body !== undefined &&
        options.body !== null &&
        !(options.body instanceof FormData) &&
        !headers.has('Content-Type')
    ) {
        headers.set('Content-Type', 'application/json');
    }

    const response = await fetch(url, {
        ...options,
        headers,
        credentials: 'same-origin',
    });

    if (!response.ok) {
        const body = await response.json().catch(() => ({}));
        const retryAfterSeconds = parseRetryAfterSeconds(
            response.headers.get('Retry-After'),
        );

        throw new ApiError(response.status, body, retryAfterSeconds);
    }

    if (response.status === 204) {
        return undefined as T;
    }

    return response.json() as Promise<T>;
}
