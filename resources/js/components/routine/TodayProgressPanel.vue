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
    routeUrl: '/routines',
    reloadOnly: ['plans', 'ops', 'date'],
});

const completionRate = computed(() => {
    if (props.totalCount === 0) {
return 0;
}

    return Math.round((props.completedCount / props.totalCount) * 100);
});
</script>

<template>
    <aside class="cd-panel min-w-0 overflow-hidden">
        <div
            class="flex min-w-0 flex-col items-center gap-4 p-3 sm:flex-row sm:items-center sm:justify-between sm:p-4"
        >
            <div class="flex w-full min-w-0 items-center justify-center gap-1 sm:w-auto sm:gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-label="前の日"
                    @click="shiftDate(-1)"
                >
                    <ChevronLeft :size="16" :stroke-width="1.6" />
                </Button>
                <div class="min-w-0 flex-1 text-center sm:min-w-[9rem] sm:flex-none">
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
                    <p
                        v-else
                        class="mt-0.5 font-sans text-[11px] text-cd-ink-muted"
                    >
                        今日のルーティン
                    </p>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-label="次の日"
                    @click="shiftDate(1)"
                >
                    <ChevronRight :size="16" :stroke-width="1.6" />
                </Button>
            </div>

            <div class="grid w-full min-w-0 grid-cols-3 gap-1.5 sm:max-w-xl sm:flex-1 sm:gap-2">
                <div
                    class="flex min-w-0 flex-col items-center rounded-xl bg-primary/10 px-2 py-2.5 text-center sm:px-3"
                >
                    <CheckCircle2
                        :size="15"
                        :stroke-width="1.7"
                        class="text-primary"
                    />
                    <p
                        class="mt-1 font-sans text-base font-semibold text-cd-ink"
                    >
                        {{ completedCount }} / {{ totalCount }}
                    </p>
                    <p class="font-sans text-[11px] text-cd-ink-muted">完了</p>
                </div>
                <div
                    class="flex min-w-0 flex-col items-center rounded-xl bg-white/60 px-2 py-2.5 text-center sm:px-3"
                >
                    <CalendarDays
                        :size="15"
                        :stroke-width="1.7"
                        class="text-cd-dawn-soft"
                    />
                    <p
                        class="mt-1 font-sans text-base font-semibold text-cd-ink"
                    >
                        {{ completionRate }}%
                    </p>
                    <p class="font-sans text-[11px] text-cd-ink-muted">進捗</p>
                </div>
                <div
                    class="flex min-w-0 flex-col items-center rounded-xl bg-white/60 px-2 py-2.5 text-center sm:px-3"
                >
                    <Clock3
                        :size="15"
                        :stroke-width="1.7"
                        class="text-cd-dawn-soft"
                    />
                    <p
                        class="mt-1 truncate font-sans text-sm font-semibold text-cd-ink"
                    >
                        {{
                            totalMinutes > 0
                                ? formatMinutesJa(totalMinutes)
                                : '—'
                        }}
                    </p>
                    <p class="font-sans text-[11px] text-cd-ink-muted">
                        予定時間
                    </p>
                </div>
            </div>
        </div>
        <div
            class="h-1.5 bg-muted"
            role="progressbar"
            :aria-valuenow="completionRate"
            aria-valuemin="0"
            aria-valuemax="100"
        >
            <div
                class="h-full bg-primary transition-all duration-500"
                :style="{ width: `${completionRate}%` }"
            />
        </div>
    </aside>
</template>
