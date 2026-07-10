<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import HeaderUserMenu from '@/components/HeaderUserMenu.vue';
import OsSidebar from '@/components/os/OsSidebar.vue';
import ProductSwitcher from '@/components/os/ProductSwitcher.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Toaster } from '@/components/ui/sonner';
import type { ProductKey } from '@/types';

withDefaults(
    defineProps<{
        title?: string;
        subtitle?: string;
    }>(),
    {
        title: '',
        subtitle: '',
    },
);

const page = usePage();
const currentProduct = computed(
    () => page.props.currentProduct as ProductKey | undefined,
);

const shellBg = computed(() => {
    if (currentProduct.value === 'kioku') {
        return 'bg-os-kioku-bg text-os-ink';
    }

    if (currentProduct.value === 'yoyu') {
        return 'bg-os-yoyu-bg text-os-ink';
    }

    return 'bg-background text-foreground';
});

const headerBg = computed(() => {
    if (currentProduct.value === 'yoyu') {
        return 'bg-os-yoyu-bg/90 backdrop-blur-sm';
    }

    if (currentProduct.value === 'kioku') {
        return 'bg-os-kioku-bg/90 backdrop-blur-sm';
    }

    return 'bg-background';
});

const titleClass = computed(() => {
    if (currentProduct.value === 'kioku') {
        return 'font-serif font-bold tracking-[0.12em] text-os-ink';
    }

    if (currentProduct.value === 'yoyu') {
        return 'font-serif text-os-yoyu';
    }

    return '';
});
</script>

<template>
    <AppShell variant="sidebar">
        <OsSidebar />
        <AppContent
            variant="sidebar"
            class="flex min-h-0 flex-1 flex-col overflow-x-hidden overflow-y-auto"
            :class="shellBg"
        >
            <header
                class="flex shrink-0 items-center justify-between gap-3 border-b border-os-line/80 px-4 py-3 md:px-6"
                :class="headerBg"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <SidebarTrigger />
                    <ProductSwitcher />
                    <div v-if="title" class="min-w-0">
                        <h1
                            class="truncate text-base font-bold tracking-wide md:text-lg"
                            :class="titleClass"
                        >
                            {{ title }}
                        </h1>
                        <p
                            v-if="subtitle"
                            class="truncate text-xs text-os-sub"
                        >
                            {{ subtitle }}
                        </p>
                    </div>
                </div>
                <HeaderUserMenu compact />
            </header>

            <main class="mx-auto w-full max-w-[1060px] flex-1 px-4 py-5 md:px-6 md:py-6">
                <slot />
            </main>
        </AppContent>
        <Toaster />
    </AppShell>
</template>
