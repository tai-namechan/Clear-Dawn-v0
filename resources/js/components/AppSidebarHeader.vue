<script setup lang="ts">
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import DashboardPageHeader from '@/components/DashboardPageHeader.vue';
import HeaderUserMenu from '@/components/HeaderUserMenu.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const { isCurrentUrl } = useCurrentUrl();
const onDashboard = computed(() => isCurrentUrl(dashboard()));
</script>

<template>
    <header
        class="flex shrink-0 border-b border-sidebar-border/70 transition-[width,height] ease-linear"
        :class="
            onDashboard
                ? 'flex-col'
                : 'h-16 items-center justify-between gap-2 px-6 group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4'
        "
    >
        <DashboardPageHeader v-if="onDashboard" />

        <template v-else>
            <div class="flex min-w-0 flex-1 items-center gap-2">
                <SidebarTrigger class="-ml-1" />
                <template v-if="breadcrumbs && breadcrumbs.length > 0">
                    <Breadcrumbs :breadcrumbs="breadcrumbs" />
                </template>
            </div>

            <HeaderUserMenu compact />
        </template>
    </header>
</template>
