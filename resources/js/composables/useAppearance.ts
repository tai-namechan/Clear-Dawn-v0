import type { ComputedRef, Ref } from 'vue';
import { computed, ref } from 'vue';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
};

export function updateTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    document.documentElement.classList.remove('dark');
}

export function initializeTheme(): void {
    updateTheme();
}

const appearance = ref<Appearance>('light');

export function useAppearance(): UseAppearanceReturn {
    const resolvedAppearance = computed<ResolvedAppearance>(() => 'light');

    function updateAppearance(value: Appearance): void {
        void value;
        appearance.value = 'light';
        updateTheme();
    }

    return {
        appearance,
        resolvedAppearance,
        updateAppearance,
    };
}
