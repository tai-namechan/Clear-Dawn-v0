<script setup lang="ts">
interface Props {
    title: string;
    subtitle?: string;
    align?: 'center' | 'left';
}

withDefaults(defineProps<Props>(), {
    subtitle: undefined,
    align: 'center',
});
</script>

<template>
    <div
        class="flex flex-col gap-2 py-2"
        :class="
            align === 'center'
                ? 'items-center py-4 text-center'
                : 'items-start text-left'
        "
    >
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
            class="cd-mask-ornament mt-1 h-8 max-w-full text-cd-ink-muted/70"
            :class="align === 'center' ? 'w-72' : 'h-6 w-48'"
        />
    </div>
</template>
