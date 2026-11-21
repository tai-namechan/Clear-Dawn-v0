<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { nextTick, onMounted, onUnmounted, ref } from 'vue';
import {
    SKY_EXTEND_SELECTOR,
    SKY_HEADER_OVERLAP_PX,
    SKY_HEADER_SELECTOR,
    SKY_HERO_SELECTOR,
    SKY_OVERLAP_PX,
    skyBackdropHeight,
} from '@/lib/skyBackdrop.mjs';

/**
 * Full-width dawn sky behind the app header and the page hero. It sits in the
 * scroll container (so it scrolls away with the hero) and grows to the bottom
 * of the hero (plus any `data-sky-extend` card), so white text always lands
 * on the sky. Pages without a hero keep only the header on the sky.
 */
const root = ref<HTMLElement | null>(null);
const height = ref(skyBackdropHeight(null));
const headerOnly = ref(false);

let targetObserver: ResizeObserver | null = null;
let removeNavigateListener: (() => void) | undefined;

function update(container: HTMLElement, targets: HTMLElement[]): void {
    if (targets.length === 0) {
        height.value = skyBackdropHeight(null);

        return;
    }

    height.value = skyBackdropHeight(
        {
            heroBottom: Math.max(
                ...targets.map(
                    (target) => target.getBoundingClientRect().bottom,
                ),
            ),
            containerTop: container.getBoundingClientRect().top,
            scrollTop: container.scrollTop,
        },
        headerOnly.value ? SKY_HEADER_OVERLAP_PX : SKY_OVERLAP_PX,
    );
}

function measure(): void {
    const container = root.value?.parentElement;

    targetObserver?.disconnect();

    if (!container) {
        return;
    }

    const hero = container.querySelector<HTMLElement>(SKY_HERO_SELECTOR);
    const header = container.querySelector<HTMLElement>(SKY_HEADER_SELECTOR);
    const targets = hero
        ? [
              hero,
              ...container.querySelectorAll<HTMLElement>(SKY_EXTEND_SELECTOR),
          ]
        : header
          ? [header]
          : [];

    headerOnly.value = !hero && Boolean(header);
    update(container, targets);

    if (targets.length > 0 && typeof ResizeObserver !== 'undefined') {
        targetObserver = new ResizeObserver(() => update(container, targets));
        targets.forEach((target) => targetObserver?.observe(target));
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
    targetObserver?.disconnect();
    removeNavigateListener?.();
    window.removeEventListener('resize', measure);
});
</script>

<template>
    <div
        ref="root"
        :class="[
            'cd-sky-backdrop',
            { 'cd-sky-backdrop--header-only': headerOnly },
        ]"
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
