<script setup lang="ts">
import { computed } from 'vue';
import { sourceTypeMeta } from '@/lib/kiokuMeta';

const props = defineProps<{
    source: string | null;
}>();

const meta = computed(() => sourceTypeMeta(props.source));
</script>

<template>
    <span
        class="inline-flex items-center gap-1 text-[11px] text-os-sub"
        :class="meta.muted ? 'opacity-50' : ''"
    >
        <component :is="meta.icon" :size="11" />
        {{ meta.label }}
    </span>
</template>
