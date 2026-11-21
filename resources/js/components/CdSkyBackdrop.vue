<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { nextTick, onMounted, onUnmounted, ref } from 'vue';
import { SKY_HERO_SELECTOR, skyBackdropHeight } from '@/lib/skyBackdrop.mjs';

/**
 * Full-width dawn sky behind the app header and the page hero. It sits in the
 * scroll container (so it scrolls away with the hero) and grows to the first
 * hero's bottom edge, so white hero text always lands on the sky.
 */
const root = ref<HTMLElement | null>(null);
const height = ref(skyBackdropHeight(null));

let heroObserver: ResizeObserver | null = null;
let removeNavigateListener: (() => void) | undefined;

function update(container: HTMLElement, hero: HTMLElement | null): void {
    height.value = skyBackdropHeight(
        hero
            ? {
                  heroBottom: hero.getBoundingClientRect().bottom,
                  containerTop: container.getBoundingClientRect().top,
                  scrollTop: container.scrollTop,
              }
            : null,
    );
}

function measure(): void {
    const container = root.value?.parentElement;

    heroObserver?.disconnect();

    if (!container) {
        return;
    }

    const hero = container.querySelector<HTMLElement>(SKY_HERO_SELECTOR);

    update(container, hero);

    if (hero && typeof ResizeObserver !== 'undefined') {
        heroObserver = new ResizeObserver(() => update(container, hero));
        heroObserver.observe(hero);
    }
}

function measureAfterRender(): void {
    void nextTick(measure);
}

onMounted(() => {
    measureAfterRender();
    removeNavigateListener = router.on('navigate', measureAfterRender);
    window.addEventListener('resize', measure);
});

onUnmounted(() => {
    heroObserver?.disconnect();
    removeNavigateListener?.();
    window.removeEventListener('resize', measure);
});
</script>

<template>
    <div
        ref="root"
        class="cd-sky-backdrop"
        :style="{ height: `${height}px` }"
        aria-hidden="true"
    >
        <img
            src="/images/backgrounds/cd-sky-dawn.webp"
            alt=""
            decoding="async"
            fetchpriority="high"
        />
    </div>
</template>
