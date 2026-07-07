import type { Exercise } from '@/types/training';

type InertiaPageResponse = {
    props?: {
        exercises?: Exercise[];
    };
};

/**
 * Inertia ページ props から種目一覧を取得する（部分リロード用）。
 */
export async function fetchExercisesFromPage(): Promise<Exercise[]> {
    const response = await fetch('/exercises', {
        headers: {
            Accept: 'application/json',
            'X-Inertia': 'true',
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
