import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { formatDateKeyJa, isTodayKey, shiftDateKey } from '@/lib/date';

interface UseDateNavigationOptions {
    date: Ref<string> | ComputedRef<string>;
    routeUrl: string;
    preserveScroll?: boolean;
    reloadOnly?: string[];
}

export function useDateNavigation({
    date,
    routeUrl,
    preserveScroll = true,
    reloadOnly,
}: UseDateNavigationOptions) {
    const formattedDate = computed(() => formatDateKeyJa(date.value));
    const isToday = computed(() => isTodayKey(date.value));

    function visitDate(dateKey: string | undefined): void {
        router.get(routeUrl, dateKey ? { date: dateKey } : {}, {
            preserveState: true,
            preserveScroll,
            ...(reloadOnly ? { only: reloadOnly } : {}),
        });
    }

    function shiftDate(days: number): void {
        visitDate(shiftDateKey(date.value, days));
    }

    function goToday(): void {
        visitDate(undefined);
    }

    function goToDate(dateKey: string): void {
        if (!dateKey || dateKey === date.value) {
            return;
        }

        visitDate(dateKey);
    }

    return { formattedDate, isToday, shiftDate, goToday, goToDate };
}
