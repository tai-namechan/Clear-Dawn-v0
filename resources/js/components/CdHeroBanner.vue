<script setup lang="ts">
withDefaults(
    defineProps<{
        eyebrow?: string;
        title: string;
        subtitle?: string;
    }>(),
    {
        eyebrow: 'CLEAR DAWN',
        subtitle: undefined,
    },
);
</script>

<template>
    <section class="cd-hero-banner" :aria-label="title">
        <div class="relative z-10">
            <p class="cd-hero-eyebrow">{{ eyebrow }}</p>
            <h1
                class="mt-2 max-w-3xl font-sans text-2xl leading-tight font-bold md:text-4xl"
            >
                {{ title }}
            </h1>
            <p
                v-if="subtitle"
                class="mt-2 max-w-2xl text-sm leading-relaxed text-cd-ink-muted md:text-base"
            >
                {{ subtitle }}
            </p>
            <div v-if="$slots.default" class="mt-5">
                <slot />
            </div>
            <div v-if="$slots.actions" class="mt-4 flex flex-wrap gap-2">
                <slot name="actions" />
            </div>
        </div>
    </section>
</template>
