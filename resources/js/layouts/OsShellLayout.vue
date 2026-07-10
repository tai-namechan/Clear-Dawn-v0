<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import HeaderUserMenu from '@/components/HeaderUserMenu.vue';
import ProductSwitcher from '@/components/os/ProductSwitcher.vue';
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

const titleClass = computed(() => {
    if (currentProduct.value === 'kioku') {
        return 'font-serif text-os-kioku';
    }

    if (currentProduct.value === 'yoyu') {
        return 'font-serif text-os-yoyu';
    }

    return '';
});
</script>

<template>
    <div class="min-h-screen" :class="shellBg">
        <header
            class="flex items-center justify-between gap-3 border-b border-os-line/80 px-4 py-3 md:px-6"
            :class="
                currentProduct === 'yoyu'
                    ? 'bg-os-yoyu-bg/90 backdrop-blur-sm'
                    : currentProduct === 'kioku'
                      ? 'bg-os-kioku-bg/90 backdrop-blur-sm'
                      : 'bg-background'
            "
        >
            <div class="flex min-w-0 items-center gap-3">
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

        <main class="mx-auto w-full max-w-[1060px] px-4 py-5 md:px-6 md:py-6">
            <slot />
        </main>

        <Toaster />
    </div>
</template>
