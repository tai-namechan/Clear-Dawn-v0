import { fetchInertiaPageProps } from '@/lib/inertiaPageFetch';
import type { Video } from '@/types/routine';

export async function fetchVideosFromPage(): Promise<Video[]> {
    const props = await fetchInertiaPageProps<{ videos?: Video[] | { data: Video[] } }>(
        '/videos',
    );

    const videos = props.videos;

    if (!videos) {
        return [];
    }

    if (Array.isArray(videos)) {
        return videos;
    }

    return videos.data ?? [];
}
