<script setup lang="ts">
type Props = {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
};

withDefaults(defineProps<Props>(), {
    variant: 'default',
});
</script>

<template>
    <header :class="variant === 'small' ? '' : 'mb-8 space-y-0.5'">
        <h2
            class="font-sans text-cd-ink"
            :class="
                variant === 'small'
                    ? 'mb-0.5 text-base font-semibold'
                    : 'text-xl font-semibold tracking-tight'
            "
        >
            {{ title }}
        </h2>
        <p
            v-if="description"
            class="font-sans text-sm text-cd-ink-muted"
        >
            {{ description }}
        </p>
    </header>
</template>
