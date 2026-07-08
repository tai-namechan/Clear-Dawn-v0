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

export async function fetchInertiaPageProps<T extends Record<string, unknown>>(
    url: string,
): Promise<T> {
    const version = getInertiaVersion();
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Inertia': 'true',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (version) {
        headers['X-Inertia-Version'] = version;
    }

    const response = await fetch(url, {
        headers,
        credentials: 'same-origin',
    });

    if (!response.ok) {
        return {} as T;
    }

    const data = (await response.json()) as InertiaPageResponse<T>;

    return data.props ?? ({} as T);
}
