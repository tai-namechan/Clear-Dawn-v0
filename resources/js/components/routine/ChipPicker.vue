<script setup lang="ts" generic="T extends string">
interface Option {
    value: T;
    label: string;
}

interface Props {
    options: Option[];
    disabled?: boolean;
    size?: 'sm' | 'md';
}

withDefaults(defineProps<Props>(), {
    disabled: false,
    size: 'md',
});

const model = defineModel<T | null>({ default: null });

function select(value: T): void {
    if (model.value === value) {
        return;
    }

    model.value = value;
}
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            class="rounded-full border font-sans tracking-[0.04em] transition-colors"
            :class="[
                size === 'sm' ? 'px-2.5 py-1 text-xs' : 'px-3 py-1.5 text-sm',
                model === option.value
                    ? 'border-primary bg-primary/10 text-primary'
                    : 'border-cd-line/80 bg-white/50 text-cd-ink-muted hover:border-cd-line hover:text-cd-ink',
                disabled ? 'pointer-events-none opacity-50' : '',
            ]"
            :disabled="disabled"
            @click="select(option.value)"
        >
            {{ option.label }}
        </button>
    </div>
</template>
