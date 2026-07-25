<script setup lang="ts">
export type PageViewTab = {
    id: string;
    label: string;
};

interface Props {
    tabs: PageViewTab[];
    modelValue: string;
    ariaLabel?: string;
}

defineProps<Props>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <div
        class="flex gap-1"
        role="tablist"
        :aria-label="ariaLabel ?? '表示切替'"
    >
        <button
            v-for="tab in tabs"
            :id="`tab-${tab.id}`"
            :key="tab.id"
            type="button"
            role="tab"
            :aria-selected="modelValue === tab.id"
            :aria-controls="`panel-${tab.id}`"
            class="relative px-4 py-2.5 font-sans text-sm font-medium transition-colors"
            :class="
                modelValue === tab.id
                    ? 'rounded-lg bg-primary text-primary-foreground'
                    : 'text-cd-ink-muted hover:text-cd-ink'
            "
            @click="emit('update:modelValue', tab.id)"
        >
            {{ tab.label }}
        </button>
    </div>
</template>
