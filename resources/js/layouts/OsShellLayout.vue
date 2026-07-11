<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AiUsageBanner from '@/components/AiUsageBanner.vue';
import HeaderUserMenu from '@/components/HeaderUserMenu.vue';
import OsSidebar from '@/components/os/OsSidebar.vue';
import ProductSwitcher from '@/components/os/ProductSwitcher.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Toaster } from '@/components/ui/sonner';
import type { ProductDefinition, ProductKey } from '@/types';

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
const products = computed(
    () => (page.props.products as ProductDefinition[] | undefined) ?? [],
);

const productName = computed(() => {
    const match = products.value.find((p) => p.key === currentProduct.value);

    return match?.name ?? '';
});

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
        return 'font-serif font-normal tracking-[0.16em] text-os-ink';
    }

    if (currentProduct.value === 'yoyu') {
        return 'font-serif font-normal tracking-[0.16em] text-os-yoyu';
    }

    return 'font-serif font-normal tracking-[0.16em]';
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
                class="flex min-h-16 shrink-0 items-center justify-between gap-2 border-b border-os-line/80 px-4 py-3 transition-[width,height] ease-linear md:px-6 md:py-4"
                :class="headerBg"
            >
                <div class="flex min-w-0 flex-1 items-center gap-2 md:gap-3">
                    <SidebarTrigger class="-ml-1 shrink-0" />
                    <h1
                        v-if="productName"
                        class="truncate text-[2rem] leading-none md:text-[2.5rem]"
                        :class="titleClass"
                    >
                        {{ productName }}
                    </h1>
                    <ProductSwitcher />
                </div>
                <HeaderUserMenu compact />
            </header>

            <AiUsageBanner />

            <main class="mx-auto w-full max-w-[1060px] flex-1 px-4 py-5 md:px-6 md:py-6">
                <slot />
            </main>
        </AppContent>
        <Toaster />
    </AppShell>
</template>
