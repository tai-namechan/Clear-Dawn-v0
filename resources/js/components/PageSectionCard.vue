<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

interface Props {
    class?: HTMLAttributes['class'];
    padding?: 'none' | 'sm' | 'md';
}

const props = withDefaults(defineProps<Props>(), {
    padding: 'md',
});
</script>

<template>
    <section
        :class="
            cn(
                'cd-panel',
                padding === 'md' && 'p-5 md:p-6',
                padding === 'sm' && 'p-4',
                padding === 'none' && 'overflow-hidden p-0',
                props.class,
            )
        "
    >
        <slot />
    </section>
</template>
