<script setup lang="ts">
interface Props {
    title: string;
    subtitle?: string;
    align?: 'center' | 'left';
    /** Dashboard 等、左寄せタイトルを一段読みやすくする */
    size?: 'default' | 'prominent';
}

withDefaults(defineProps<Props>(), {
    subtitle: undefined,
    align: 'center',
    size: 'default',
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
                    : size === 'prominent'
                      ? 'text-4xl leading-tight tracking-[0.14em] md:text-[2.625rem]'
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
                    : size === 'prominent'
                      ? 'cd-title-rule mt-1.5 w-44'
                      : 'cd-title-rule w-40'
            "
        />
    </div>
</template>
