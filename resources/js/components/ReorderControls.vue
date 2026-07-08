<script setup lang="ts">
import { ArrowDown, ArrowUp } from '@lucide/vue';
import { Button } from '@/components/ui/button';

interface Props {
    index: number;
    length: number;
    itemLabel?: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    up: [];
    down: [];
}>();
</script>

<template>
    <div class="flex shrink-0 items-center gap-1">
        <Button
            type="button"
            variant="ghost"
            size="icon"
            :disabled="props.index === 0"
            :aria-label="itemLabel ? `${itemLabel} を上へ` : '上へ移動'"
            @click="emit('up')"
        >
            <ArrowUp :size="15" :stroke-width="1.6" />
        </Button>
        <Button
            type="button"
            variant="ghost"
            size="icon"
            :disabled="props.index === props.length - 1"
            :aria-label="itemLabel ? `${itemLabel} を下へ` : '下へ移動'"
            @click="emit('down')"
        >
            <ArrowDown :size="15" :stroke-width="1.6" />
        </Button>
    </div>
</template>
