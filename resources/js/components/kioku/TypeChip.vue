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
        class="inline-flex items-center gap-1 rounded-full font-medium"
        :class="small ? 'px-2 py-0.5 text-[10.5px]' : 'px-2.5 py-1 text-[11.5px]'"
        :style="{ color: meta.color, background: meta.bg }"
    >
        <component :is="meta.icon" :size="small ? 10 : 12" />
        {{ meta.label }}
    </span>
</template>
