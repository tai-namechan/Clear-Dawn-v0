<script setup lang="ts">
import {
    CalendarDays,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock3,
} from '@lucide/vue';
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
    <aside class="min-w-0 overflow-hidden rounded-2xl bg-[#F7F6FA]">
        <div
            class="flex min-w-0 flex-col gap-3 p-3 sm:flex-row sm:items-center sm:gap-3 sm:p-3.5"
        >
            <div
                class="flex min-w-0 flex-1 items-center gap-2 rounded-xl bg-white/70 px-2.5 py-2"
            >
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    class="shrink-0 text-cd-ink-muted"
                    aria-label="前の日"
                    @click="shiftDate(-1)"
                >
                    <ChevronLeft :size="16" :stroke-width="1.6" />
                </Button>
                <CalendarDays
                    :size="16"
                    :stroke-width="1.7"
                    class="shrink-0 text-primary"
                    aria-hidden="true"
                />
                <div class="min-w-0 flex-1 text-left">
                    <p
                        class="truncate font-sans text-sm font-semibold text-cd-ink"
                    >
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
                    class="shrink-0 text-cd-ink-muted"
                    aria-label="次の日"
                    @click="shiftDate(1)"
                >
                    <ChevronRight :size="16" :stroke-width="1.6" />
                </Button>
            </div>

            <div class="grid min-w-0 grid-cols-3 gap-2 sm:max-w-md sm:flex-1">
                <div
                    class="flex min-w-0 flex-col items-center rounded-xl bg-white px-2 py-2 text-center shadow-sm"
                >
                    <CheckCircle2
                        :size="14"
                        :stroke-width="1.8"
                        class="text-primary"
                    />
                    <p class="mt-1 font-sans text-sm font-semibold text-cd-ink">
                        {{ completedCount }} / {{ totalCount }}
                    </p>
                    <p class="font-sans text-[10px] text-cd-ink-muted">完了</p>
                </div>
                <div
                    class="flex min-w-0 flex-col items-center rounded-xl bg-white px-2 py-2 text-center shadow-sm"
                >
                    <span
                        class="flex size-3.5 items-center justify-center rounded-full border-[1.5px] border-primary/35"
                        aria-hidden="true"
                    >
                        <span class="block size-1.5 rounded-full bg-primary/50" />
                    </span>
                    <p class="mt-1 font-sans text-sm font-semibold text-cd-ink">
                        {{ completionRate }}%
                    </p>
                    <p class="font-sans text-[10px] text-cd-ink-muted">進捗</p>
                </div>
                <div
                    class="flex min-w-0 flex-col items-center rounded-xl bg-white px-2 py-2 text-center shadow-sm"
                >
                    <Clock3
                        :size="14"
                        :stroke-width="1.7"
                        class="text-cd-ink-muted"
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
                    <p class="font-sans text-[10px] text-cd-ink-muted">
                        予定時間
                    </p>
                </div>
            </div>
        </div>
    </aside>
</template>
