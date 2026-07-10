<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
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

/** SVG circle: r=54 → circumference ≈ 339.3 */
const RING = 2 * Math.PI * 54;

const ringOffset = computed(() => {
    const progress = props.totalCount === 0 ? 0 : props.completedCount / props.totalCount;

    return RING * (1 - progress);
});
</script>

<template>
    <aside
        class="relative flex w-full shrink-0 flex-col overflow-hidden rounded-2xl px-6 py-7 text-white lg:w-[240px]"
        style="
            background:
                radial-gradient(
                    circle at 40% 8%,
                    rgba(255, 255, 255, 0.14),
                    transparent 28%
                ),
                linear-gradient(
                    165deg,
                    var(--cd-dawn-deep) 0%,
                    var(--cd-dawn-mid) 42%,
                    var(--cd-dawn-soft) 100%
                );
        "
    >
        <div class="relative z-10 flex flex-col gap-6">
            <div>
                <p class="font-sans text-sm font-medium tracking-wide text-white/90">
                    今日のルーティン
                </p>
                <div class="mt-2 flex items-center gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        class="text-white/80 hover:bg-white/10 hover:text-white"
                        aria-label="前の日"
                        @click="shiftDate(-1)"
                    >
                        <ChevronLeft :size="16" :stroke-width="1.6" />
                    </Button>
                    <div class="min-w-0 flex-1 text-center">
                        <p class="font-sans text-sm text-white/85">
                            {{ formattedDate }}
                        </p>
                        <button
                            v-if="!isToday"
                            type="button"
                            class="mt-0.5 font-sans text-[0.65rem] text-white/70 underline-offset-2 hover:underline"
                            @click="goToday"
                        >
                            今日に戻る
                        </button>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        class="text-white/80 hover:bg-white/10 hover:text-white"
                        aria-label="次の日"
                        @click="shiftDate(1)"
                    >
                        <ChevronRight :size="16" :stroke-width="1.6" />
                    </Button>
                </div>
            </div>

            <div class="flex flex-col items-center gap-3 py-2">
                <div class="relative size-[148px]">
                    <svg
                        class="size-full -rotate-90"
                        viewBox="0 0 120 120"
                        aria-hidden="true"
                    >
                        <circle
                            cx="60"
                            cy="60"
                            r="54"
                            fill="none"
                            stroke="rgba(255,255,255,0.18)"
                            stroke-width="5"
                        />
                        <circle
                            cx="60"
                            cy="60"
                            r="54"
                            fill="none"
                            stroke="rgba(255,255,255,0.92)"
                            stroke-width="5"
                            stroke-linecap="round"
                            :stroke-dasharray="RING"
                            :stroke-dashoffset="ringOffset"
                            class="transition-[stroke-dashoffset] duration-700 ease-out"
                        />
                    </svg>
                    <div
                        class="absolute inset-0 flex flex-col items-center justify-center"
                    >
                        <p class="font-sans text-3xl font-semibold tracking-tight">
                            {{ completedCount }}
                            <span class="text-xl font-normal text-white/70">
                                / {{ totalCount }}
                            </span>
                        </p>
                        <p class="mt-0.5 font-sans text-xs text-white/70">
                            完了
                        </p>
                    </div>
                </div>
                <p class="font-sans text-sm text-white/80">
                    完了率 {{ completionRate }}%
                </p>
            </div>

            <div class="border-t border-white/15 pt-4">
                <p class="font-sans text-xs text-white/65">合計予定時間</p>
                <p class="mt-1 font-sans text-lg font-medium tracking-tight">
                    {{
                        totalMinutes > 0
                            ? formatMinutesJa(totalMinutes)
                            : '—'
                    }}
                </p>
            </div>

            <blockquote
                class="mt-auto rounded-xl bg-white/10 px-4 py-4 font-sans text-xs leading-relaxed text-white/85"
            >
                <span class="mb-2 block text-lg leading-none text-white/35"
                    >“</span
                >
                小さな積み重ねが、昨日の自分を超えていく。
                <footer class="mt-3 text-right text-white/55">
                    — Clear Dawn —
                </footer>
            </blockquote>
        </div>
    </aside>
</template>
