<script setup lang="ts">
import HeaderUserMenu from '@/components/HeaderUserMenu.vue';
import ProductSwitcher from '@/components/os/ProductSwitcher.vue';
import { Toaster } from '@/components/ui/sonner';

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
</script>

<template>
    <div class="min-h-screen bg-background text-foreground">
        <header
            class="flex items-center justify-between gap-3 border-b border-border px-4 py-3 md:px-6"
        >
            <div class="flex min-w-0 items-center gap-3">
                <ProductSwitcher />
                <div v-if="title" class="min-w-0">
                    <h1 class="truncate text-base font-semibold md:text-lg">
                        {{ title }}
                    </h1>
                    <p
                        v-if="subtitle"
                        class="truncate text-xs text-muted-foreground"
                    >
                        {{ subtitle }}
                    </p>
                </div>
            </div>
            <HeaderUserMenu compact />
        </header>

        <main class="mx-auto w-full max-w-3xl px-4 py-16 md:px-6">
            <slot />
        </main>

        <Toaster />
    </div>
</template>
