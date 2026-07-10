<script setup lang="ts">
import { computed } from 'vue';
import {
    formatMinutes,
    TUB_LABEL,
    yoyuCalc,
    type CalEvent,
    type TubStatus,
    type YoyuTaskLike,
} from '@/lib/yoyuCalc';

const props = defineProps<{
    nowMs: number;
    calendar: CalEvent[];
    doneEventIds: string[];
    tasks: YoyuTaskLike[];
}>();

const calc = computed(() =>
    yoyuCalc(props.nowMs, props.calendar, props.doneEventIds, props.tasks),
);

const mood = computed(() => {
    const map: Record<
        TubStatus,
        { color: string; bg: string; msg: string; water: string }
    > = {
        yoyu: {
            color: '#43A860',
            bg: '#E8F5EC',
            msg: 'まだ入ります。焦らずいきましょう。',
            water: '#4FB3A9',
        },
        tapu: {
            color: '#129488',
            bg: '#E4F4F2',
            msg: '理想の詰まり具合。あふれる一歩手前です。',
            water: '#4FB3A9',
        },
        over: {
            color: '#D9534F',
            bg: '#FBE8E7',
            msg: '詰めすぎです。予定かタスクを1つ手放しましょう。',
            water: '#E2705F',
        },
    };

    return map[calc.value.status];
});

const waterY = computed(() => {
    const topY = 60;
    const botY = 158;
    const lv = Math.min(calc.value.level, 1);

    return botY - lv * (botY - topY);
});

function wavePath(offset: number): string {
    const y = waterY.value + offset;

    return `M-80 ${y} q20 -7 40 0 t40 0 t40 0 t40 0 t40 0 t40 0 t40 0 t40 0 t40 0 t40 0 t40 0 V178 H-80 Z`;
}
</script>

<template>
    <section
        class="rounded-[18px] border p-[18px] pb-3.5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        :style="{
            background: mood.bg,
            borderColor: mood.color + '44',
        }"
    >
        <div class="flex flex-wrap items-baseline justify-between gap-1.5">
            <span
                class="font-serif text-lg font-bold tracking-wide"
                :style="{ color: mood.color }"
            >
                {{ TUB_LABEL[calc.status] }}
            </span>
            <span class="text-xs text-os-sub">{{ mood.msg }}</span>
        </div>

        <svg
            viewBox="0 0 320 196"
            class="mx-auto mt-0.5 block w-full max-w-[330px]"
            role="img"
            :aria-label="`余裕メーター: ${TUB_LABEL[calc.status]}`"
        >
            <defs>
                <clipPath id="tubClip">
                    <path
                        d="M52 58 H268 L258 148 Q256 160 244 160 H76 Q64 160 62 148 Z"
                    />
                </clipPath>
            </defs>

            <ellipse
                v-if="calc.status === 'over'"
                cx="160"
                cy="188"
                rx="112"
                ry="6"
                :fill="mood.water"
                opacity="0.22"
            />

            <g clip-path="url(#tubClip)">
                <rect
                    x="40"
                    y="50"
                    width="240"
                    height="120"
                    fill="#FFFFFF"
                    opacity="0.55"
                />
                <path
                    class="os-wave-slow"
                    :fill="mood.water"
                    opacity="0.4"
                    :d="wavePath(6)"
                />
                <path
                    class="os-wave"
                    :fill="mood.water"
                    opacity="0.9"
                    :d="wavePath(0)"
                />
            </g>

            <g
                v-if="calc.status !== 'over'"
                :transform="`translate(148 ${waterY - 13})`"
            >
                <g class="os-bob">
                    <ellipse cx="10" cy="10" rx="11" ry="8" fill="#F7C948" />
                    <circle cx="20" cy="2" r="6" fill="#F7C948" />
                    <path d="M25 1 l7 2 -7 3 Z" fill="#E2705F" />
                    <circle cx="21.5" cy="0.5" r="1" fill="#26303A" />
                </g>
            </g>

            <g v-else>
                <path
                    d="M52 56 q-9 28 -6 58"
                    :stroke="mood.water"
                    stroke-width="5"
                    fill="none"
                    stroke-linecap="round"
                    opacity="0.75"
                />
                <path
                    d="M268 56 q9 28 6 58"
                    :stroke="mood.water"
                    stroke-width="5"
                    fill="none"
                    stroke-linecap="round"
                    opacity="0.75"
                />
            </g>

            <path
                d="M46 52 H274 L262 150 Q259 166 242 166 H78 Q61 166 58 150 Z"
                fill="none"
                stroke="#26303A"
                stroke-width="3.5"
                stroke-linejoin="round"
            />
            <line
                x1="38"
                y1="52"
                x2="60"
                y2="52"
                stroke="#26303A"
                stroke-width="3.5"
                stroke-linecap="round"
            />
            <line
                x1="260"
                y1="52"
                x2="282"
                y2="52"
                stroke="#26303A"
                stroke-width="3.5"
                stroke-linecap="round"
            />
            <path
                d="M84 166 l-9 16 M236 166 l9 16"
                stroke="#26303A"
                stroke-width="3.5"
                stroke-linecap="round"
            />
        </svg>

        <div
            class="mt-1 flex flex-wrap justify-center gap-4 text-xs text-os-sub"
        >
            <span>
                予定＋移動＋タスク：
                <b class="text-os-ink">{{ formatMinutes(calc.busy) }}</b>
            </span>
            <span>
                自由時間：
                <b class="text-os-ink">{{ formatMinutes(calc.free) }}</b>
            </span>
        </div>
    </section>
</template>

<style scoped>
.os-wave {
    animation: os-wave-move 5s linear infinite;
}
.os-wave-slow {
    animation: os-wave-move 8.5s linear infinite reverse;
}
.os-bob {
    animation: os-bob-y 3s ease-in-out infinite;
}
@keyframes os-wave-move {
    to {
        transform: translateX(-80px);
    }
}
@keyframes os-bob-y {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-3px);
    }
}
@media (prefers-reduced-motion: reduce) {
    .os-wave,
    .os-wave-slow,
    .os-bob {
        animation: none !important;
    }
}
</style>
