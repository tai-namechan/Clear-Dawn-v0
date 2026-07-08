export class ApiError extends Error {
    constructor(
        public readonly status: number,
        public readonly body: unknown,
    ) {
        super(`API request failed with status ${status}`);
        this.name = 'ApiError';
    }
}

function getXsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
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

        throw new ApiError(response.status, body);
    }

    if (response.status === 204) {
        return undefined as T;
    }

    return response.json() as Promise<T>;
}
