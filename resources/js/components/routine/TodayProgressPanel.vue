<script setup lang="ts">
import {
    CalendarDays,
    Check,
    ChevronLeft,
    ChevronRight,
    Minus,
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
    /** Today's sessions for the checklist next to the ring. */
    sessions?: { id: string; title: string; done: boolean }[];
}

const props = withDefaults(defineProps<Props>(), {
    sessions: () => [],
});

const CHECKLIST_LIMIT = 4;
const checklist = computed(() => props.sessions.slice(0, CHECKLIST_LIMIT));
const hiddenCount = computed(() =>
    Math.max(0, props.sessions.length - CHECKLIST_LIMIT),
);
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
        <!-- Mockup: "本日の実行" ring and a checklist of today's sessions -->
        <div class="flex items-center gap-4 px-4 pt-4 pb-3">
            <CdProgressRing
                :percent="completionRate"
                size="lg"
                tone="gold"
                :label="`本日の実行 ${completedCount} / ${totalCount}`"
            >
                <span class="cd-meal-ring-caption">本日の実行</span>
                <span class="cd-meal-ring-value"
                    >{{ completedCount }}<small>/ {{ totalCount }}</small></span
                >
            </CdProgressRing>
            <div class="min-w-0 flex-1">
                <ul v-if="checklist.length > 0" class="flex flex-col">
                    <li
                        v-for="item in checklist"
                        :key="item.id"
                        class="flex items-center gap-2 border-b border-[#f0edf5] py-1.5 last:border-b-0"
                    >
                        <Check
                            v-if="item.done"
                            :size="15"
                            :stroke-width="2.4"
                            class="shrink-0 text-[var(--cd-gold)]"
                            aria-label="完了"
                        />
                        <Minus
                            v-else
                            :size="15"
                            :stroke-width="2"
                            class="shrink-0 text-cd-ink-muted/60"
                            aria-label="未完了"
                        />
                        <span
                            class="min-w-0 flex-1 truncate font-sans text-sm text-cd-ink"
                            :class="{ 'text-cd-ink-muted': item.done }"
                            >{{ item.title }}</span
                        >
                    </li>
                </ul>
                <p v-else class="font-sans text-sm text-cd-ink-muted">
                    今日のセッションはまだありません
                </p>
                <p
                    class="mt-1.5 font-sans text-[11px] text-cd-ink-muted tabular-nums"
                >
                    <template v-if="hiddenCount > 0"
                        >ほか {{ hiddenCount }} 件 ·
                    </template>
                    予定
                    {{ totalMinutes > 0 ? formatMinutesJa(totalMinutes) : '—' }}
                </p>
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
