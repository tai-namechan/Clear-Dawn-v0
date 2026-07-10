<script setup lang="ts">
import { computed } from 'vue';
import { memoryTypeMeta } from '@/lib/kiokuMeta';

const props = withDefaults(
    defineProps<{
        type: string | null;
        small?: boolean;
    }>(),
    {
        small: false,
    },
);

const meta = computed(() => memoryTypeMeta(props.type));
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full font-bold"
        :class="small ? 'px-2 py-0.5 text-[11px]' : 'px-2.5 py-1 text-xs'"
        :style="{ color: meta.color, background: meta.bg }"
    >
        <component :is="meta.icon" :size="small ? 11 : 13" />
        {{ meta.label }}
    </span>
</template>
