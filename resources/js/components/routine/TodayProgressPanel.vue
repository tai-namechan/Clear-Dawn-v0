<script setup lang="ts">
import { CalendarDays, CheckCircle2, ChevronLeft, ChevronRight, Clock3 } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useDateNavigation } from '@/composables/useDateNavigation';
import { formatMinutesJa } from '@/lib/todayPlanDisplay';

interface Props {
    date: string;
    completedCount: number;
    totalCount: number;
    totalMinutes: number;
}

const props = defineProps<Props>();
const dateRef = computed(() => props.date);

const { formattedDate, isToday, shiftDate, goToday } = useDateNavigation({
    date: dateRef,
    routeUrl: '/today',
    reloadOnly: ['plans', 'date'],
});

const completionRate = computed(() => {
    if (props.totalCount === 0) {
return 0;
}

    return Math.round((props.completedCount / props.totalCount) * 100);
});
</script>

<template>
    <aside class="cd-panel overflow-hidden">
        <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2">
                <Button type="button" variant="ghost" size="icon-sm" aria-label="前の日" @click="shiftDate(-1)">
                    <ChevronLeft :size="16" :stroke-width="1.6" />
                </Button>
                <div class="min-w-[9rem] text-center">
                    <p class="font-sans text-sm font-semibold text-cd-ink">
                        {{ formattedDate }}
                    </p>
                    <button
                        v-if="!isToday"
                        type="button"
                        class="mt-0.5 font-sans text-[11px] text-primary hover:underline"
                        @click="goToday"
                    >
                        今日に戻る
                    </button>
                    <p v-else class="mt-0.5 font-sans text-[11px] text-cd-ink-muted">
                        今日のルーティン
                    </p>
                </div>
                <Button type="button" variant="ghost" size="icon-sm" aria-label="次の日" @click="shiftDate(1)">
                    <ChevronRight :size="16" :stroke-width="1.6" />
                </Button>
            </div>

            <div class="grid flex-1 grid-cols-3 gap-2 sm:max-w-xl">
                <div class="rounded-xl bg-primary/10 px-3 py-2.5">
                    <CheckCircle2 :size="15" :stroke-width="1.7" class="text-primary" />
                    <p class="mt-1 font-sans text-base font-semibold text-cd-ink">
                        {{ completedCount }} / {{ totalCount }}
                    </p>
                    <p class="font-sans text-[11px] text-cd-ink-muted">完了</p>
                </div>
                <div class="rounded-xl bg-white/60 px-3 py-2.5">
                    <CalendarDays :size="15" :stroke-width="1.7" class="text-cd-dawn-soft" />
                    <p class="mt-1 font-sans text-base font-semibold text-cd-ink">{{ completionRate }}%</p>
                    <p class="font-sans text-[11px] text-cd-ink-muted">進捗</p>
                </div>
                <div class="rounded-xl bg-white/60 px-3 py-2.5">
                    <Clock3 :size="15" :stroke-width="1.7" class="text-cd-dawn-soft" />
                    <p class="mt-1 truncate font-sans text-sm font-semibold text-cd-ink">
                        {{ totalMinutes > 0 ? formatMinutesJa(totalMinutes) : '—' }}
                    </p>
                    <p class="font-sans text-[11px] text-cd-ink-muted">予定時間</p>
                </div>
            </div>
        </div>
        <div class="h-1.5 bg-muted" role="progressbar" :aria-valuenow="completionRate" aria-valuemin="0" aria-valuemax="100">
            <div class="h-full bg-primary transition-all duration-500" :style="{ width: `${completionRate}%` }" />
        </div>
    </aside>
</template>
