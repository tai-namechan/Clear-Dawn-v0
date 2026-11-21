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
    <!-- Same pill segment as the home and records tabs (cd-segment). -->
    <div
        class="cd-segment w-full font-sans"
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
            @click="emit('update:modelValue', tab.id)"
        >
            {{ tab.label }}
        </button>
    </div>
</template>
