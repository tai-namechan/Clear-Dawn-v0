<script setup lang="ts">
import {
    Activity,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Clock3,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import CdProgressRing from '@/components/CdProgressRing.vue';
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
const dateInputRef = ref<HTMLInputElement | null>(null);

const { formattedDate, isToday, shiftDate, goToday, goToDate } =
    useDateNavigation({
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

function openDatePicker(): void {
    const input = dateInputRef.value;

    if (!input) {
        return;
    }

    if (typeof input.showPicker === 'function') {
        input.showPicker();

        return;
    }

    input.click();
}

function onDatePicked(event: Event): void {
    const value = (event.target as HTMLInputElement).value;
    goToDate(value);
}
</script>

<template>
    <aside class="min-w-0 overflow-hidden rounded-2xl" aria-label="今日の進捗">
        <!-- Mockup: completion ring, progress and planned time -->
        <div
            class="grid grid-cols-[auto_minmax(0,1fr)_minmax(0,1fr)] items-center gap-2 px-3 pt-3 pb-2 sm:px-4"
        >
            <CdProgressRing
                :percent="completionRate"
                size="md"
                :label="`完了 ${completedCount} / ${totalCount}`"
            >
                <span class="cd-progress-ring-value"
                    >{{ completedCount }}/{{ totalCount }}</span
                >
                <span class="cd-progress-ring-caption">完了</span>
            </CdProgressRing>
            <div class="flex min-w-0 flex-col items-center text-center">
                <Activity
                    :size="20"
                    :stroke-width="1.8"
                    class="text-[var(--cd-hero-primary)]"
                    aria-hidden="true"
                />
                <p
                    class="mt-1 font-sans text-2xl font-bold text-cd-ink tabular-nums"
                >
                    {{ completionRate }}%
                </p>
                <p class="font-sans text-xs text-cd-ink-muted">進捗</p>
            </div>
            <div
                class="flex min-w-0 flex-col items-center border-l border-[#efecf5] text-center"
            >
                <Clock3
                    :size="20"
                    :stroke-width="1.8"
                    class="text-[var(--cd-hero-primary)]"
                    aria-hidden="true"
                />
                <p
                    class="mt-1 truncate font-sans text-2xl font-bold text-cd-ink tabular-nums"
                >
                    {{ totalMinutes > 0 ? formatMinutesJa(totalMinutes) : '—' }}
                </p>
                <p class="font-sans text-xs text-cd-ink-muted">予定時間</p>
            </div>
        </div>

        <div
            class="flex min-w-0 items-center gap-1 border-t border-[#efecf5] px-2 py-1.5"
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

            <div
                class="flex min-w-0 flex-1 flex-col items-center justify-center text-center"
            >
                <div class="inline-flex max-w-full items-center gap-1.5">
                    <button
                        type="button"
                        class="inline-flex shrink-0 items-center justify-center rounded-md p-1 text-[var(--cd-hero-primary)] transition-colors hover:bg-[var(--cd-hero-primary-soft)]"
                        aria-label="日付を選択"
                        @click="openDatePicker"
                    >
                        <CalendarDays :size="16" :stroke-width="1.7" />
                    </button>
                    <p
                        class="truncate font-sans text-sm font-semibold text-cd-ink"
                    >
                        {{ formattedDate }}
                    </p>
                    <button
                        v-if="!isToday"
                        type="button"
                        class="font-sans text-[11px] font-semibold text-[var(--cd-hero-primary)] hover:underline"
                        @click="goToday"
                    >
                        今日に戻る
                    </button>
                </div>
                <input
                    ref="dateInputRef"
                    type="date"
                    class="sr-only"
                    :value="date"
                    tabindex="-1"
                    aria-hidden="true"
                    @change="onDatePicked"
                />
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
    </aside>
</template>
