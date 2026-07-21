<script setup lang="ts">
import { computed } from 'vue';

type Mood = 'incomplete' | 'shortfall' | 'safe';

const props = withDefaults(
    defineProps<{
        mood: Mood;
        /** 余裕の相対量 0〜1（safe 時の小判枚数に使う） */
        level?: number;
        caption?: string;
    }>(),
    {
        level: 0.55,
        caption: undefined,
    },
);

/** 箱の中に入る小判の枚数（0〜5） */
const coinCount = computed(() => {
    if (props.mood === 'incomplete') {
        return 0;
    }

    if (props.mood === 'shortfall') {
        return 1;
    }

    const n = Math.round(1 + Math.min(1, Math.max(0, props.level)) * 4);

    return Math.min(5, Math.max(2, n));
});

const coinCountLabel = computed(() => {
    if (props.mood === 'incomplete') {
        return '—';
    }

    return `${coinCount.value}枚`;
});

const resolvedCaption = computed(() => {
    if (props.caption) {
        return props.caption;
    }

    if (props.mood === 'incomplete') {
        return '小判箱はまだ空です。口座と収支を入れると、余裕が枚数で見えてきます。';
    }

    if (props.mood === 'shortfall') {
        return '箱の中は小判1枚。いまの計算では余裕が足りていません。';
    }

    if (coinCount.value >= 4) {
        return `箱に小判が${coinCount.value}枚。余裕に厚みがあります。`;
    }

    return `箱に小判が${coinCount.value}枚。いまの余裕です。`;
});

const statusLabel = computed(() => {
    if (props.mood === 'incomplete') {
        return 'セットアップ中';
    }

    if (props.mood === 'shortfall') {
        return '余裕が不足';
    }

    return '余裕あり';
});

/**
 * 箱の底（見た目の奥行き）に並ぶ小判の中心座標。
 * 枚数に応じて中央寄りに積む。
 */
const coinLayouts: Record<
    number,
    Array<{ cx: number; cy: number; rot: number }>
> = {
    1: [{ cx: 100, cy: 108, rot: -4 }],
    2: [
        { cx: 86, cy: 110, rot: -10 },
        { cx: 114, cy: 108, rot: 8 },
    ],
    3: [
        { cx: 78, cy: 112, rot: -12 },
        { cx: 100, cy: 106, rot: 2 },
        { cx: 122, cy: 110, rot: 10 },
    ],
    4: [
        { cx: 74, cy: 114, rot: -14 },
        { cx: 94, cy: 108, rot: -4 },
        { cx: 112, cy: 106, rot: 6 },
        { cx: 130, cy: 112, rot: 12 },
    ],
    5: [
        { cx: 70, cy: 116, rot: -16 },
        { cx: 88, cy: 110, rot: -6 },
        { cx: 104, cy: 104, rot: 2 },
        { cx: 118, cy: 108, rot: 8 },
        { cx: 134, cy: 114, rot: 14 },
    ],
};

const coins = computed(() => coinLayouts[coinCount.value] ?? []);
</script>

<template>
    <figure
        class="money-margin-koban flex flex-col items-center gap-3"
        role="img"
        :aria-label="`${statusLabel}。小判箱に${mood === 'incomplete' ? '入っていない' : coinCountLabel}`"
    >
        <div class="relative w-full max-w-[280px]">
            <svg
                class="money-margin-koban__art h-auto w-full"
                viewBox="0 0 200 168"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
                aria-hidden="true"
            >
                <defs>
                    <linearGradient
                        id="mk-box-wood"
                        x1="40"
                        y1="70"
                        x2="160"
                        y2="150"
                        gradientUnits="userSpaceOnUse"
                    >
                        <stop stop-color="#c4a574" />
                        <stop offset="0.45" stop-color="#a67c4a" />
                        <stop offset="1" stop-color="#7a5632" />
                    </linearGradient>
                    <linearGradient
                        id="mk-box-inner"
                        x1="100"
                        y1="78"
                        x2="100"
                        y2="128"
                        gradientUnits="userSpaceOnUse"
                    >
                        <stop stop-color="#5c3d22" />
                        <stop offset="1" stop-color="#3d2814" />
                    </linearGradient>
                    <linearGradient
                        id="mk-lid"
                        x1="48"
                        y1="28"
                        x2="148"
                        y2="70"
                        gradientUnits="userSpaceOnUse"
                    >
                        <stop stop-color="#d4b896" />
                        <stop offset="1" stop-color="#9a7348" />
                    </linearGradient>
                    <radialGradient
                        id="mk-koban-face"
                        cx="38%"
                        cy="32%"
                        r="68%"
                    >
                        <stop stop-color="#ffe9a8" />
                        <stop offset="0.55" stop-color="#e8c45a" />
                        <stop offset="1" stop-color="#b8862a" />
                    </radialGradient>
                    <filter
                        id="mk-soft"
                        x="-20%"
                        y="-20%"
                        width="140%"
                        height="140%"
                    >
                        <feDropShadow
                            dx="0"
                            dy="2"
                            stdDeviation="2"
                            flood-color="#1a1208"
                            flood-opacity="0.22"
                        />
                    </filter>
                </defs>

                <!-- 床の影 -->
                <ellipse
                    cx="100"
                    cy="154"
                    rx="58"
                    ry="7"
                    fill="#1a1208"
                    opacity="0.1"
                />

                <!-- 蓋（開いている・奥） -->
                <g class="money-margin-koban__lid" filter="url(#mk-soft)">
                    <path
                        d="M52 62 L78 22 L148 34 L128 72 Z"
                        fill="url(#mk-lid)"
                        stroke="#6b4a28"
                        stroke-width="1.2"
                    />
                    <path
                        d="M78 22 L148 34"
                        stroke="#fff8e8"
                        stroke-opacity="0.35"
                        stroke-width="1"
                    />
                    <rect
                        x="96"
                        y="40"
                        width="18"
                        height="6"
                        rx="1.5"
                        transform="rotate(-18 105 43)"
                        fill="#8a6238"
                    />
                </g>

                <!-- 箱：奥壁・側壁・底（中が見える） -->
                <g class="money-margin-koban__box">
                    <!-- 奥壁 -->
                    <path
                        d="M48 78 L72 62 L128 62 L152 78 L152 118 L48 118 Z"
                        fill="url(#mk-box-inner)"
                    />
                    <!-- 左側面 -->
                    <path
                        d="M48 78 L48 138 L62 148 L62 88 Z"
                        fill="#8b6438"
                        opacity="0.95"
                    />
                    <!-- 右側面 -->
                    <path
                        d="M152 78 L152 138 L138 148 L138 88 Z"
                        fill="#6b4a28"
                        opacity="0.95"
                    />
                    <!-- 底（内側） -->
                    <path
                        d="M62 88 L138 88 L138 148 L62 148 Z"
                        fill="url(#mk-box-inner)"
                    />
                    <!-- 底の木目 -->
                    <path
                        d="M70 100 H130 M74 112 H126 M78 124 H122 M82 136 H118"
                        stroke="#2a1a0c"
                        stroke-opacity="0.25"
                        stroke-width="1"
                        stroke-linecap="round"
                    />
                </g>

                <!-- 中身：小判（箱の内側に積む） -->
                <g class="money-margin-koban__coins">
                    <template v-if="mood === 'incomplete'">
                        <!-- 空の箱：入るはずの小判のシルエット -->
                        <ellipse
                            cx="100"
                            cy="112"
                            rx="22"
                            ry="14"
                            fill="none"
                            stroke="#c9b896"
                            stroke-width="1.5"
                            stroke-dasharray="4 3"
                            opacity="0.45"
                        />
                    </template>
                    <template v-else>
                        <g
                            v-for="(coin, index) in coins"
                            :key="`in-box-${index}`"
                            :transform="`translate(${coin.cx}, ${coin.cy}) rotate(${coin.rot})`"
                            filter="url(#mk-soft)"
                        >
                            <g
                                class="money-margin-koban__coin"
                                :style="{ animationDelay: `${index * 90}ms` }"
                            >
                                <ellipse
                                    cx="0"
                                    cy="0"
                                    rx="20"
                                    ry="13"
                                    fill="url(#mk-koban-face)"
                                    stroke="#8a6a20"
                                    stroke-width="1.1"
                                />
                                <ellipse
                                    cx="0"
                                    cy="0"
                                    rx="13"
                                    ry="8"
                                    fill="none"
                                    stroke="#fff6d0"
                                    stroke-opacity="0.45"
                                    stroke-width="1"
                                />
                                <text
                                    x="0"
                                    y="3.5"
                                    text-anchor="middle"
                                    font-size="9"
                                    font-weight="700"
                                    fill="#7a5818"
                                    font-family="serif"
                                >
                                    両
                                </text>
                            </g>
                        </g>
                    </template>
                </g>

                <!-- 箱の前縁（小判の下半分を少し隠して「箱の中」に見せる） -->
                <g class="money-margin-koban__front">
                    <path
                        d="M48 118 L62 148 L138 148 L152 118 L152 138 L138 158 L62 158 L48 138 Z"
                        fill="url(#mk-box-wood)"
                        stroke="#5c3d22"
                        stroke-width="1.2"
                    />
                    <path
                        d="M56 128 H144"
                        stroke="#fff4dc"
                        stroke-opacity="0.2"
                        stroke-width="1.5"
                        stroke-linecap="round"
                    />
                    <!-- 前縁の厚みライン（開口部） -->
                    <path
                        d="M50 120 L62 146 L138 146 L150 120"
                        fill="none"
                        stroke="#3d2814"
                        stroke-opacity="0.35"
                        stroke-width="1"
                    />
                </g>
            </svg>

            <div
                class="money-margin-koban__badge pointer-events-none absolute top-2 right-2 rounded-md border px-2.5 py-1 text-center shadow-sm backdrop-blur-sm"
                :class="{
                    'border-border/70 bg-background/90 text-muted-foreground':
                        mood === 'incomplete',
                    'border-destructive/30 bg-background/90 text-destructive':
                        mood === 'shortfall',
                    'border-os-yoyu/35 bg-background/90 text-os-yoyu':
                        mood === 'safe',
                }"
            >
                <p
                    class="text-[10px] leading-none font-medium tracking-wide opacity-80"
                >
                    箱の中
                </p>
                <p
                    class="mt-0.5 text-sm leading-none font-semibold tabular-nums"
                >
                    {{ coinCountLabel }}
                </p>
            </div>
        </div>

        <figcaption
            class="max-w-sm text-center text-sm leading-relaxed text-muted-foreground"
        >
            {{ resolvedCaption }}
        </figcaption>
    </figure>
</template>

<style scoped>
.money-margin-koban__lid {
    transform-origin: 100px 68px;
    animation: mk-lid-ease 4.5s ease-in-out infinite;
}

.money-margin-koban__coin {
    animation: mk-coin-settle 0.55s ease-out both;
}

@media (prefers-reduced-motion: reduce) {
    .money-margin-koban__lid,
    .money-margin-koban__coin {
        animation: none;
    }
}

@keyframes mk-lid-ease {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(1.5px);
    }
}

@keyframes mk-coin-settle {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}
</style>
