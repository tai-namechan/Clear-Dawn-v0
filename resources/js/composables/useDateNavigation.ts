import { computed, type ComputedRef, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { parseDateKey, todayKey, toDateKey } from '@/lib/date';

type DateNavigationOptions = {
    dateProp: Ref<string> | ComputedRef<string>;
    routeUrl: string;
    reloadOnly?: string[];
};

export function useDateNavigation({
    dateProp,
    routeUrl,
    reloadOnly,
}: DateNavigationOptions) {
    const formattedDate = computed(() => {
        const d = parseDateKey(dateProp.value);

        return d.toLocaleDateString('ja-JP', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            weekday: 'short',
        });
    });

    const isToday = computed(() => dateProp.value === todayKey());

    function shiftDate(days: number): void {
        const current = parseDateKey(dateProp.value);
        current.setDate(current.getDate() + days);

        router.get(
            routeUrl,
            { date: toDateKey(current) },
            {
                preserveState: true,
                preserveScroll: true,
                ...(reloadOnly ? { only: reloadOnly } : {}),
            },
        );
    }

    function goToday(): void {
        router.get(
            routeUrl,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                ...(reloadOnly ? { only: reloadOnly } : {}),
            },
        );
    }

    return { formattedDate, isToday, shiftDate, goToday };
}
