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
        class="flex gap-1 border-b border-cd-line"
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
            class="relative -mb-px px-4 py-2.5 font-sans text-sm font-medium transition-colors"
            :class="
                modelValue === tab.id
                    ? 'text-primary'
                    : 'text-cd-ink-muted hover:text-cd-ink'
            "
            @click="emit('update:modelValue', tab.id)"
        >
            {{ tab.label }}
            <span
                v-if="modelValue === tab.id"
                class="absolute inset-x-2 bottom-0 h-0.5 rounded-full bg-primary"
            />
        </button>
    </div>
</template>
