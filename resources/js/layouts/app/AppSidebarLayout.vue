<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AiUsageBanner from '@/components/AiUsageBanner.vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import CdMobileNav from '@/components/CdMobileNav.vue';
import CdSkyBackdrop from '@/components/CdSkyBackdrop.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();

/** The matrix (dashboard) keeps the marble page background under the sky. */
const isMatrixPage = computed(() => page.component === 'Dashboard');

const contentClass = computed(() =>
    [
        'cd-clear-app cd-paper-bg flex min-h-0 flex-1 flex-col overflow-x-hidden overflow-y-auto',
        isMatrixPage.value ? 'cd-marble-page' : '',
    ]
        .filter(Boolean)
        .join(' '),
);
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" :class="contentClass">
            <CdSkyBackdrop />
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <AiUsageBanner />
            <slot />
            <CdMobileNav />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
