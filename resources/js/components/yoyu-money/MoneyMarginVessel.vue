<script setup lang="ts">
import { computed } from 'vue';

type MoneyMarginMood = 'safe' | 'shortfall' | 'incomplete';

interface Props {
    mood: MoneyMarginMood;
    /** 0–1 fill for visual only; shortfall uses low water */
    level?: number;
    label?: string;
}

const props = withDefaults(defineProps<Props>(), {
    level: 0.55,
    label: undefined,
});

const palette = computed(() => {
    if (props.mood === 'shortfall') {
        return {
            bg: '#FBF6F1',
            border: '#8A5A3B44',
            accent: '#8A5A3B',
            water: '#C4A484',
            title: 'お金の余裕 · 不足の見込み',
            msg: '支払いのタイミングや金額を見直す余地があります。',
        };
    }

    if (props.mood === 'incomplete') {
        return {
            bg: '#F5F6F2',
            border: '#E5E0D2',
            accent: '#6E6A5E',
            water: '#D5D0C4',
            title: 'お金の余裕 · 準備中',
            msg: '設定が進むと、安全に使える金額が見えてきます。',
        };
    }

    return {
        bg: '#E4F4F2',
        border: '#12948844',
        accent: '#129488',
        water: '#4FB3A9',
        title: 'お金の余裕 · まだあります',
        msg: '焦らず、次の判断を比べられます。',
    };
});

const fillRatio = computed(() => {
    if (props.mood === 'incomplete') {
        return 0.12;
    }

    if (props.mood === 'shortfall') {
        return 0.14;
    }

    return Math.min(0.92, Math.max(0.28, props.level));
});

const waterY = computed(() => {
    const topY = 52;
    const botY = 148;

    return botY - fillRatio.value * (botY - topY);
});

function wavePath(offset: number): string {
    const y = waterY.value + offset;

    return `M40 ${y} q18 -6 36 0 t36 0 t36 0 t36 0 t36 0 t36 0 V156 H40 Z`;
}

const ariaLabel = computed(
    () => props.label ?? `${palette.value.title}。${palette.value.msg}`,
);
</script>

<template>
    <section
        class="rounded-[18px] border p-4 shadow-[0_1px_3px_rgba(38,48,58,0.05)] sm:p-[18px]"
        :style="{
            background: palette.bg,
            borderColor: palette.border,
        }"
    >
        <div class="flex flex-wrap items-baseline justify-between gap-1.5">
            <span
                class="font-serif text-base font-bold tracking-wide sm:text-lg"
                :style="{ color: palette.accent }"
            >
                {{ palette.title }}
            </span>
            <span class="text-xs text-os-sub">{{ palette.msg }}</span>
        </div>

        <svg
            viewBox="0 0 280 180"
            class="mx-auto mt-1 block w-full max-w-[280px]"
            role="img"
            :aria-label="ariaLabel"
        >
            <defs>
                <clipPath id="moneyVesselClip">
                    <path
                        d="M78 46 H202 L214 148 Q216 158 204 158 H76 Q64 158 66 148 Z"
                    />
                </clipPath>
            </defs>

            <!-- soft ground -->
            <ellipse
                cx="140"
                cy="168"
                rx="88"
                ry="6"
                :fill="palette.water"
                opacity="0.18"
            />

            <g clip-path="url(#moneyVesselClip)">
                <rect
                    x="60"
                    y="40"
                    width="160"
                    height="130"
                    fill="#FFFFFF"
                    opacity="0.5"
                />
                <path
                    class="money-wave-slow"
                    :fill="palette.water"
                    opacity="0.35"
                    :d="wavePath(5)"
                />
                <path
                    class="money-wave"
                    :fill="palette.water"
                    opacity="0.88"
                    :d="wavePath(0)"
                />
            </g>

            <!-- vessel outline -->
            <path
                d="M74 44 H206 L218 150 Q220 164 204 164 H76 Q60 164 62 150 Z"
                fill="none"
                stroke="#2B2924"
                stroke-width="3"
                stroke-linejoin="round"
            />
            <!-- rim -->
            <path
                d="M68 44 H212"
                fill="none"
                stroke="#2B2924"
                stroke-width="3"
                stroke-linecap="round"
            />

            <!-- incomplete: dashed “yet to fill” cue -->
            <g v-if="mood === 'incomplete'" opacity="0.7">
                <circle
                    cx="140"
                    cy="96"
                    r="18"
                    fill="none"
                    stroke="#6E6A5E"
                    stroke-width="2"
                    stroke-dasharray="4 4"
                />
                <path
                    d="M140 88 V104 M132 96 H148"
                    stroke="#6E6A5E"
                    stroke-width="2"
                    stroke-linecap="round"
                />
            </g>

            <!-- shortfall: gentle empty cue (not alarming) -->
            <g v-else-if="mood === 'shortfall'">
                <path
                    d="M118 78 q22 -18 44 0"
                    fill="none"
                    stroke="#8A5A3B"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    opacity="0.65"
                />
                <circle cx="128" cy="92" r="2.2" fill="#8A5A3B" opacity="0.7" />
                <circle cx="152" cy="92" r="2.2" fill="#8A5A3B" opacity="0.7" />
            </g>

            <!-- safe: small floating leaf / bob -->
            <g v-else :transform="`translate(128 ${waterY - 10})`">
                <g class="money-bob">
                    <ellipse cx="12" cy="8" rx="14" ry="6" fill="#F7C948" />
                    <path
                        d="M4 7 q8 -10 16 0"
                        fill="none"
                        stroke="#E8A317"
                        stroke-width="1.5"
                    />
                </g>
            </g>
        </svg>
    </section>
</template>

<style scoped>
.money-wave {
    animation: money-wave-move 5.5s linear infinite;
}
.money-wave-slow {
    animation: money-wave-move 9s linear infinite reverse;
}
.money-bob {
    animation: money-bob-y 3.2s ease-in-out infinite;
}
@keyframes money-wave-move {
    to {
        transform: translateX(-36px);
    }
}
@keyframes money-bob-y {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-3px);
    }
}
@media (prefers-reduced-motion: reduce) {
    .money-wave,
    .money-wave-slow,
    .money-bob {
        animation: none !important;
    }
}
</style>
