import { ChartLine, CircleCheck, Home, Settings, Utensils } from '@lucide/vue';
import type { Component } from 'vue';
import type { ClearDawnNavItem } from '@/lib/clearDawnNavigation.mjs';

/** サイドバーと下部ナビで共通のアイコン。 */
export const clearDawnNavIcons: Record<ClearDawnNavItem['key'], Component> = {
    dashboard: Home,
    routines: CircleCheck,
    records: ChartLine,
    meals: Utensils,
    settings: Settings,
};
