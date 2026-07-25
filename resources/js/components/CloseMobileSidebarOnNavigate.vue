<script setup lang="ts">
import { useSidebar } from '@/components/ui/sidebar';
import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';

const { isMobile, setOpenMobile } = useSidebar();

const closeIfMobile = (): void => {
    if (isMobile.value) {
        setOpenMobile(false);
    }
};

let removeNavigateListener: (() => void) | undefined;

onMounted(() => {
    removeNavigateListener = router.on('navigate', closeIfMobile);
});

onUnmounted(() => {
    removeNavigateListener?.();
});
</script>

<template>
    <span class="sr-only" aria-hidden="true" />
</template>
