import { router } from '@inertiajs/vue3';
import { computed, type ComputedRef, type Ref } from 'vue';
import {
    formatDateKeyJa,
    isTodayKey,
    shiftDateKey,
} from '@/lib/date';

interface UseDateNavigationOptions {
    date: Ref<string> | ComputedRef<string>;
    routeUrl: string;
    preserveScroll?: boolean;
}

export function useDateNavigation({
    date,
    routeUrl,
    preserveScroll = true,
}: UseDateNavigationOptions) {
    const formattedDate = computed(() => formatDateKeyJa(date.value));
    const isToday = computed(() => isTodayKey(date.value));

    function shiftDate(days: number): void {
        router.get(
            routeUrl,
            { date: shiftDateKey(date.value, days) },
            { preserveState: true, preserveScroll },
        );
    }

    function goToday(): void {
        router.get(routeUrl, {}, { preserveState: true, preserveScroll });
    }

    return { formattedDate, isToday, shiftDate, goToday };
}
