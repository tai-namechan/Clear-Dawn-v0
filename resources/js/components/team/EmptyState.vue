<script setup lang="ts">
defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center"
    >
        <p class="font-medium text-slate-800">{{ title }}</p>
        <p v-if="description" class="mt-2 text-sm text-slate-500">
            {{ description }}
        </p>
    </div>
</template>
