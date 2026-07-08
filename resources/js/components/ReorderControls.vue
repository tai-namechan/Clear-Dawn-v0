<script setup lang="ts">
import { ChevronDown, ChevronUp } from '@lucide/vue';
import { Button } from '@/components/ui/button';

interface Props {
    index: number;
    length: number;
}

defineProps<Props>();

const emit = defineEmits<{
    up: [];
    down: [];
}>();
</script>

<template>
    <div class="flex flex-col gap-0.5">
        <Button
            variant="ghost"
            size="icon"
            class="size-7"
            :disabled="index === 0"
            aria-label="上へ移動"
            @click="emit('up')"
        >
            <ChevronUp class="size-4" />
        </Button>
        <Button
            variant="ghost"
            size="icon"
            class="size-7"
            :disabled="index >= length - 1"
            aria-label="下へ移動"
            @click="emit('down')"
        >
            <ChevronDown class="size-4" />
        </Button>
    </div>
</template>
