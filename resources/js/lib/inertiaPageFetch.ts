type InertiaPageResponse<T extends Record<string, unknown>> = {
    props?: T;
};

function getInertiaVersion(): string | undefined {
    const app = document.getElementById('app');

    if (!app?.dataset.page) {
        return undefined;
    }

    try {
        return JSON.parse(app.dataset.page).version as string;
    } catch {
        return undefined;
    }
}

async function fetchInertiaJson(
    url: string,
    includeVersion: boolean,
): Promise<Response> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Inertia': 'true',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (includeVersion) {
        const version = getInertiaVersion();

        if (version) {
            headers['X-Inertia-Version'] = version;
        }
    }

    return fetch(url, {
        headers,
        credentials: 'same-origin',
    });
}

/**
 * Inertia ページの props を JSON で取得する。
 * アセットバージョン不一致 (409) のときは version ヘッダなしで再試行する。
 */
export async function fetchInertiaPageProps<T extends Record<string, unknown>>(
    url: string,
): Promise<T> {
    let response = await fetchInertiaJson(url, true);

    // 409 = Inertia asset version mismatch. Retry without version so pickers still work.
    if (response.status === 409) {
        response = await fetchInertiaJson(url, false);
    }

    if (!response.ok) {
        return {} as T;
    }

    const data = (await response.json()) as InertiaPageResponse<T>;

    return data.props ?? ({} as T);
}
