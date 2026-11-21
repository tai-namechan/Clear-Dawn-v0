<script setup lang="ts">
import { computed } from 'vue';

/**
 * Progress ring for white cards. `percent` fills the ring (clamped 0-100);
 * the caller decides what text to show inside (e.g. the real "106%").
 */
const props = withDefaults(
    defineProps<{
        percent: number;
        size?: 'md' | 'lg' | 'xl';
        tone?: 'violet' | 'gold';
        label?: string;
    }>(),
    {
        size: 'md',
        tone: 'violet',
        label: undefined,
    },
);

const fill = computed(() => Math.max(0, Math.min(100, props.percent)));
</script>

<template>
    <div
        class="cd-progress-ring"
        :class="[
            `cd-progress-ring--${size}`,
            { 'cd-progress-ring--gold': tone === 'gold' },
        ]"
        :style="{ '--cd-ring-fill': `${fill * 3.6}deg` }"
        role="img"
        :aria-label="label"
    >
        <div class="cd-progress-ring-inner">
            <slot />
        </div>
    </div>
</template>
