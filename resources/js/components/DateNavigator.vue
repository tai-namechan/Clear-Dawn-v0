<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import { useDateNavigation } from '@/composables/useDateNavigation';
import { Button } from '@/components/ui/button';

interface Props {
    date: string;
    routeUrl: string;
    reloadOnly?: string[];
}

const props = defineProps<Props>();

const dateRef = computed(() => props.date);

const { formattedDate, isToday, shiftDate, goToday } = useDateNavigation({
    date: dateRef,
    routeUrl: props.routeUrl,
    reloadOnly: props.reloadOnly,
});
</script>

<template>
    <div
        class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-cd-line/80 bg-white/60 px-4 py-3"
    >
        <div class="flex items-center gap-2">
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label="前の日"
                @click="shiftDate(-1)"
            >
                <ChevronLeft :size="18" :stroke-width="1.6" />
            </Button>

            <div class="text-center">
                <p class="font-serif text-base tracking-[0.1em] text-cd-ink">
                    {{ formattedDate }}
                </p>
                <button
                    v-if="!isToday"
                    type="button"
                    class="mt-0.5 font-sans text-xs text-primary underline-offset-2 hover:underline"
                    @click="goToday"
                >
                    今日に戻る
                </button>
            </div>

            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label="次の日"
                @click="shiftDate(1)"
            >
                <ChevronRight :size="18" :stroke-width="1.6" />
            </Button>
        </div>

        <div v-if="$slots.actions" class="flex items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
