<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { toRef } from 'vue';
import { useDateNavigation } from '@/composables/useDateNavigation';
import { Button } from '@/components/ui/button';

interface Props {
    date: string;
    routeUrl: string;
    reloadOnly?: string[];
}

const props = defineProps<Props>();

const { formattedDate, isToday, shiftDate, goToday } = useDateNavigation({
    dateProp: toRef(props, 'date'),
    routeUrl: props.routeUrl,
    reloadOnly: props.reloadOnly,
});
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <Button
                variant="outline"
                size="icon"
                aria-label="前の日"
                @click="shiftDate(-1)"
            >
                <ChevronLeft class="size-4" />
            </Button>
            <h2
                class="min-w-[10rem] text-center font-serif text-lg tracking-[0.08em] text-cd-ink"
            >
                {{ formattedDate }}
            </h2>
            <Button
                variant="outline"
                size="icon"
                aria-label="次の日"
                @click="shiftDate(1)"
            >
                <ChevronRight class="size-4" />
            </Button>
            <Button
                v-if="!isToday"
                variant="ghost"
                size="sm"
                class="text-cd-ink-muted"
                @click="goToday"
            >
                今日
            </Button>
        </div>
        <div v-if="$slots.actions" class="flex items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
