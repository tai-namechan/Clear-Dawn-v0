import { apiFetch } from '@/lib/apiFetch';
import type { Video } from '@/types/routine';

/**
 * 動画一覧を JSON API で取得する（Inertia 409 を避ける）。
 */
export async function fetchVideosFromPage(): Promise<Video[]> {
    const result = await apiFetch<{ videos: Video[] }>('/videos', {
        headers: {
            Accept: 'application/json',
        },
    });

    return result.videos ?? [];
}
