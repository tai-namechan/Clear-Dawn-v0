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
        class="flex flex-col gap-2"
        :class="
            align === 'center'
                ? 'items-center py-4 text-center'
                : 'items-start py-1 text-left'
        "
    >
        <h1
            class="font-serif font-normal text-primary"
            :class="
                align === 'center'
                    ? 'text-4xl tracking-[0.12em] md:text-5xl'
                    : 'text-3xl leading-tight tracking-[0.14em] md:text-4xl'
            "
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
            class="max-w-full"
            :class="
                align === 'center'
                    ? 'cd-mask-ornament mt-1 h-8 w-72 text-cd-ink-muted/60'
                    : 'cd-title-rule w-40'
            "
        />
    </div>
</template>
