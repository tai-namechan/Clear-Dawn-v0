<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AiUsageBanner from '@/components/AiUsageBanner.vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import CdMobileNav from '@/components/CdMobileNav.vue';
import CdOfflineBanner from '@/components/CdOfflineBanner.vue';
import { Toaster } from '@/components/ui/sonner';
import { backgroundScene } from '@/lib/backgroundScene.mjs';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();

/** Wa-modern background per screen (docs/clear-dawn-hero-design-guidelines.md v3). */
const scene = computed(() => backgroundScene(page.component));

const contentClass = computed(
    () =>
        `cd-clear-app cd-wa-bg cd-wa-${scene.value} flex min-h-0 flex-1 flex-col overflow-x-hidden overflow-y-auto`,
);
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" :class="contentClass">
            <CdOfflineBanner />
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <AiUsageBanner />
            <slot />
            <CdMobileNav />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
