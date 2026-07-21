<script setup lang="ts">
interface Props {
    label: string;
    tone?: 'neutral' | 'positive' | 'caution' | 'info';
}

withDefaults(defineProps<Props>(), {
    tone: 'neutral',
});
</script>

<template>
    <span
        class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold"
        :class="{
            'bg-os-yoyu-soft text-os-yoyu': tone === 'positive',
            'bg-[#F3E8DF] text-[#8A5A3B]': tone === 'caution',
            'bg-[#E8EEF5] text-[#3E5688]': tone === 'info',
            'bg-os-yoyu-bg text-os-sub': tone === 'neutral',
        }"
    >
        {{ label }}
    </span>
</template>
