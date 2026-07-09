<script setup lang="ts" generic="T extends string">
interface Option {
    value: T;
    label: string;
}

interface Props {
    options: Option[];
    disabled?: boolean;
    size?: 'sm' | 'md';
    allowClear?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
    size: 'md',
    allowClear: false,
});

const model = defineModel<T | null>({ default: null });

function select(value: T): void {
    if (model.value === value) {
        if (props.allowClear) {
            model.value = null;
        }

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
            class="rounded-full border font-sans font-medium transition-colors"
            :class="[
                size === 'sm' ? 'px-2.5 py-1 text-xs' : 'px-3 py-1.5 text-sm',
                model === option.value
                    ? 'border-primary bg-primary text-primary-foreground shadow-sm'
                    : 'border-cd-line bg-white text-cd-ink hover:border-primary/40 hover:bg-primary/5 hover:text-primary',
                disabled ? 'pointer-events-none opacity-50' : '',
            ]"
            :disabled="disabled"
            @click="select(option.value)"
        >
            {{ option.label }}
        </button>
    </div>
</template>
