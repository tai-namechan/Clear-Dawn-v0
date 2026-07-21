<script setup lang="ts">
import { computed } from 'vue';

type MoneyMarginMood = 'safe' | 'shortfall' | 'incomplete';

interface Props {
    mood: MoneyMarginMood;
    /** 0–1 visual density for how many koban to emphasize (safe only) */
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
            coin: '#D8B56A',
            coinEdge: '#B8923E',
            title: 'お金の余裕 · 不足の見込み',
            msg: '支払いのタイミングや金額を見直す余地があります。',
        };
    }

    if (props.mood === 'incomplete') {
        return {
            bg: '#F5F6F2',
            border: '#E5E0D2',
            accent: '#6E6A5E',
            coin: '#D5D0C4',
            coinEdge: '#A39E8F',
            title: 'お金の余裕 · 準備中',
            msg: '設定が進むと、安全に使える金額が見えてきます。',
        };
    }

    return {
        bg: '#E4F4F2',
        border: '#12948844',
        accent: '#129488',
        coin: '#E8C56A',
        coinEdge: '#C9A227',
        title: 'お金の余裕 · まだあります',
        msg: '焦らず、次の判断を比べられます。',
    };
});

/** How many solid koban to show in the safe state (3–5). */
const safeCount = computed(() => {
    if (props.level < 0.4) {
        return 3;
    }

    if (props.level < 0.7) {
        return 4;
    }

    return 5;
});

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
            <!-- soft mat -->
            <ellipse
                cx="140"
                cy="158"
                rx="96"
                ry="10"
                :fill="palette.coin"
                opacity="0.18"
            />

            <!-- incomplete: dashed koban outlines waiting to be filled -->
            <g v-if="mood === 'incomplete'">
                <g
                    v-for="(pos, i) in [
                        { x: 88, y: 92, r: -18 },
                        { x: 140, y: 86, r: 0 },
                        { x: 192, y: 92, r: 18 },
                    ]"
                    :key="`empty-${i}`"
                    :transform="`translate(${pos.x} ${pos.y}) rotate(${pos.r})`"
                    opacity="0.75"
                >
                    <ellipse
                        cx="0"
                        cy="0"
                        rx="28"
                        ry="18"
                        fill="none"
                        :stroke="palette.coinEdge"
                        stroke-width="2.2"
                        stroke-dasharray="5 4"
                    />
                    <ellipse
                        cx="0"
                        cy="0"
                        rx="14"
                        ry="8"
                        fill="none"
                        :stroke="palette.coinEdge"
                        stroke-width="1.5"
                        stroke-dasharray="3 3"
                    />
                </g>
                <text
                    x="140"
                    y="132"
                    text-anchor="middle"
                    font-size="12"
                    fill="#6E6A5E"
                    font-family="serif"
                >
                    まだ数えはじめ
                </text>
            </g>

            <!-- shortfall: one thin koban, quiet not shaming -->
            <g v-else-if="mood === 'shortfall'">
                <g transform="translate(140 96)" class="koban-rest">
                    <ellipse
                        cx="0"
                        cy="4"
                        rx="34"
                        ry="10"
                        fill="#2B2924"
                        opacity="0.06"
                    />
                    <ellipse
                        cx="0"
                        cy="0"
                        rx="36"
                        ry="22"
                        :fill="palette.coin"
                        :stroke="palette.coinEdge"
                        stroke-width="2.5"
                    />
                    <ellipse
                        cx="0"
                        cy="0"
                        rx="22"
                        ry="12"
                        fill="none"
                        :stroke="palette.coinEdge"
                        stroke-width="1.8"
                        opacity="0.7"
                    />
                    <circle
                        cx="0"
                        cy="0"
                        r="4"
                        :fill="palette.coinEdge"
                        opacity="0.55"
                    />
                </g>
                <text
                    x="140"
                    y="138"
                    text-anchor="middle"
                    font-size="12"
                    fill="#8A5A3B"
                    font-family="serif"
                >
                    小判が少なく見えます
                </text>
            </g>

            <!-- safe: a small pile / fan of koban -->
            <g v-else>
                <g
                    v-for="(pos, i) in [
                        { x: 96, y: 108, r: -22, delay: '0s' },
                        { x: 124, y: 98, r: -8, delay: '0.15s' },
                        { x: 152, y: 94, r: 6, delay: '0.3s' },
                        { x: 178, y: 102, r: 18, delay: '0.45s' },
                        { x: 140, y: 118, r: 0, delay: '0.2s' },
                    ].slice(0, safeCount)"
                    :key="`koban-${i}`"
                    :transform="`translate(${pos.x} ${pos.y}) rotate(${pos.r})`"
                    class="koban-bob"
                    :style="{ animationDelay: pos.delay }"
                >
                    <ellipse
                        cx="0"
                        cy="3"
                        rx="30"
                        ry="8"
                        fill="#2B2924"
                        opacity="0.07"
                    />
                    <ellipse
                        cx="0"
                        cy="0"
                        rx="30"
                        ry="19"
                        :fill="palette.coin"
                        :stroke="palette.coinEdge"
                        stroke-width="2.4"
                    />
                    <ellipse
                        cx="0"
                        cy="0"
                        rx="18"
                        ry="10"
                        fill="none"
                        :stroke="palette.coinEdge"
                        stroke-width="1.6"
                        opacity="0.75"
                    />
                    <circle
                        cx="0"
                        cy="0"
                        r="3.5"
                        :fill="palette.coinEdge"
                        opacity="0.5"
                    />
                </g>
                <text
                    x="140"
                    y="152"
                    text-anchor="middle"
                    font-size="12"
                    fill="#129488"
                    font-family="serif"
                >
                    使える小判があります
                </text>
            </g>
        </svg>
    </section>
</template>

<style scoped>
.koban-bob {
    animation: koban-bob-y 3.4s ease-in-out infinite;
    transform-box: fill-box;
    transform-origin: center;
}
.koban-rest {
    animation: koban-rest-y 4s ease-in-out infinite;
}
@keyframes koban-bob-y {
    0%,
    100% {
        translate: 0 0;
    }
    50% {
        translate: 0 -3px;
    }
}
@keyframes koban-rest-y {
    0%,
    100% {
        translate: 0 0;
    }
    50% {
        translate: 0 -1.5px;
    }
}
@media (prefers-reduced-motion: reduce) {
    .koban-bob,
    .koban-rest {
        animation: none !important;
    }
}
</style>
