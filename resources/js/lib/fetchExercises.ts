import { usePage } from '@inertiajs/vue3';
import type { Exercise } from '@/types/training';

type InertiaPageResponse = {
    props?: {
        exercises?: Exercise[];
    };
};

/**
 * Inertia ページ props から種目一覧を取得する。
 * X-Inertia-Version を付与しないと 409 になる。
 */
export function useFetchExercises() {
    const page = usePage();

    async function fetchExercises(): Promise<Exercise[]> {
        const response = await fetch('/exercises', {
            headers: {
                Accept: 'application/json',
                'X-Inertia': 'true',
                'X-Inertia-Version': String(page.version),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return [];
        }

        const data = (await response.json()) as InertiaPageResponse;

        return data.props?.exercises ?? [];
    }

    return { fetchExercises };
}
