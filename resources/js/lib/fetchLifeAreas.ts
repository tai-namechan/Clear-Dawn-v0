import { fetchInertiaPageProps } from '@/lib/inertiaPageFetch';
import type { LifeArea } from '@/types/matrix';

export async function fetchLifeAreasFromPage(): Promise<LifeArea[]> {
    const props = await fetchInertiaPageProps<{ lifeAreas?: LifeArea[] }>(
        '/life-areas',
    );

    return props.lifeAreas ?? [];
}
