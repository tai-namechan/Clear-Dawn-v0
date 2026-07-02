<script setup lang="ts">
interface Props {
    title: string;
    subtitle?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="flex flex-col items-center gap-2 py-4 text-center">
        <h1
            class="font-serif text-4xl font-normal tracking-[0.18em] text-primary md:text-5xl"
        >
            {{ title }}
        </h1>
        <p
            v-if="subtitle"
            class="font-serif text-sm tracking-[0.12em] text-muted-foreground"
        >
            {{ subtitle }}
        </p>
        <div
            aria-hidden="true"
            class="cd-mask-ornament mt-1 h-8 w-72 max-w-full text-cd-ink-muted/70"
        />
    </div>
</template>
