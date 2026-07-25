<script setup lang="ts">
import { CalendarDays, ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useDateNavigation } from '@/composables/useDateNavigation';

interface Props {
    date: string;
    routeUrl: string;
    reloadOnly?: string[];
    /**
     * ページシェル右上向け。枠付きカードではなく、カレンダーアイコン＋日付のコンパクト表示。
     */
    compact?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    reloadOnly: undefined,
    compact: false,
});

const dateRef = computed(() => props.date);
const dateInputRef = ref<HTMLInputElement | null>(null);

const { formattedDate, isToday, shiftDate, goToday, goToDate } =
    useDateNavigation({
        date: dateRef,
        routeUrl: props.routeUrl,
        reloadOnly: props.reloadOnly,
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
    <div
        v-if="compact"
        class="inline-flex items-center gap-0.5"
        role="group"
        aria-label="日付"
    >
        <Button
            type="button"
            variant="ghost"
            size="icon"
            class="size-8"
            aria-label="前の日"
            @click="shiftDate(-1)"
        >
            <ChevronLeft :size="16" :stroke-width="1.6" />
        </Button>

        <div class="flex min-w-0 flex-col items-center px-1 text-center">
            <div class="inline-flex items-center gap-1.5">
                <button
                    type="button"
                    class="inline-flex shrink-0 items-center justify-center rounded-md p-1 text-cd-ink-muted transition-colors hover:bg-muted hover:text-cd-ink"
                    aria-label="日付を選択"
                    @click="openDatePicker"
                >
                    <CalendarDays :size="16" :stroke-width="1.6" />
                </button>
                <p
                    class="font-sans text-sm font-semibold tracking-tight text-cd-ink whitespace-nowrap"
                >
                    {{ formattedDate }}
                </p>
            </div>
            <button
                v-if="!isToday"
                type="button"
                class="font-sans text-[11px] font-medium text-primary underline-offset-2 hover:underline"
                @click="goToday"
            >
                今日に戻る
            </button>
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
            size="icon"
            class="size-8"
            aria-label="次の日"
            @click="shiftDate(1)"
        >
            <ChevronRight :size="16" :stroke-width="1.6" />
        </Button>
    </div>

    <div
        v-else
        class="flex w-full items-center gap-3 rounded-2xl border border-cd-line bg-white px-4 py-3"
        :class="$slots.actions ? 'justify-between' : 'justify-center'"
    >
        <template v-if="$slots.actions">
            <div class="flex min-w-0 items-center gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="前の日"
                    @click="shiftDate(-1)"
                >
                    <ChevronLeft :size="18" :stroke-width="1.6" />
                </Button>

                <div class="min-w-0 text-center">
                    <div class="inline-flex items-center gap-1.5">
                        <button
                            type="button"
                            class="inline-flex shrink-0 items-center justify-center rounded-md p-1 text-cd-ink-muted transition-colors hover:bg-muted hover:text-cd-ink"
                            aria-label="日付を選択"
                            @click="openDatePicker"
                        >
                            <CalendarDays :size="16" :stroke-width="1.6" />
                        </button>
                        <p
                            class="font-sans text-base font-semibold tracking-tight text-cd-ink md:text-lg"
                        >
                            {{ formattedDate }}
                        </p>
                    </div>
                    <button
                        v-if="!isToday"
                        type="button"
                        class="mt-0.5 font-sans text-xs font-medium text-primary underline-offset-2 hover:underline"
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

            <div class="flex shrink-0 items-center gap-2">
                <slot name="actions" />
            </div>
        </template>

        <template v-else>
            <div class="mx-auto flex items-center justify-center gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="前の日"
                    @click="shiftDate(-1)"
                >
                    <ChevronLeft :size="18" :stroke-width="1.6" />
                </Button>

                <div class="min-w-0 text-center">
                    <div class="inline-flex items-center gap-1.5">
                        <button
                            type="button"
                            class="inline-flex shrink-0 items-center justify-center rounded-md p-1 text-cd-ink-muted transition-colors hover:bg-muted hover:text-cd-ink"
                            aria-label="日付を選択"
                            @click="openDatePicker"
                        >
                            <CalendarDays :size="16" :stroke-width="1.6" />
                        </button>
                        <p
                            class="font-sans text-base font-semibold tracking-tight text-cd-ink md:text-lg"
                        >
                            {{ formattedDate }}
                        </p>
                    </div>
                    <button
                        v-if="!isToday"
                        type="button"
                        class="mt-0.5 font-sans text-xs font-medium text-primary underline-offset-2 hover:underline"
                        @click="goToday"
                    >
                        今日に戻る
                    </button>
                    <p
                        v-else
                        class="mt-0.5 font-sans text-xs font-medium text-cd-ink-muted"
                    >
                        今日
                    </p>
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
        </template>

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
</template>
