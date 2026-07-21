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
 * 箱の底に並ぶ小判の中心座標。
 * 枚数に応じて中央寄りに積む。
 */
const coinLayouts: Record<
    number,
    Array<{ cx: number; cy: number; rot: number }>
> = {
    1: [{ cx: 100, cy: 112, rot: -3 }],
    2: [
        { cx: 84, cy: 114, rot: -9 },
        { cx: 116, cy: 112, rot: 7 },
    ],
    3: [
        { cx: 76, cy: 116, rot: -11 },
        { cx: 100, cy: 110, rot: 1 },
        { cx: 124, cy: 114, rot: 9 },
    ],
    4: [
        { cx: 72, cy: 118, rot: -13 },
        { cx: 92, cy: 112, rot: -4 },
        { cx: 112, cy: 110, rot: 5 },
        { cx: 132, cy: 116, rot: 11 },
    ],
    5: [
        { cx: 68, cy: 120, rot: -14 },
        { cx: 86, cy: 114, rot: -6 },
        { cx: 104, cy: 108, rot: 1 },
        { cx: 120, cy: 112, rot: 7 },
        { cx: 136, cy: 118, rot: 13 },
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
        <div class="relative w-full max-w-[300px]">
            <div
                class="money-margin-koban__stage overflow-hidden rounded-xl border border-[#d8cfc0]/80 bg-[#f7f2e8] shadow-sm"
            >
                <svg
                    class="money-margin-koban__art h-auto w-full"
                    viewBox="0 0 200 176"
                    fill="none"
                    xmlns="http://www.w3.org/2000/svg"
                    aria-hidden="true"
                >
                    <defs>
                        <!-- 和紙トーン -->
                        <radialGradient id="mk-washi" cx="50%" cy="42%" r="70%">
                            <stop stop-color="#fffaf0" />
                            <stop offset="0.55" stop-color="#f3ebe0" />
                            <stop offset="1" stop-color="#e7ddd0" />
                        </radialGradient>
                        <!-- 漆箱 -->
                        <linearGradient
                            id="mk-urushi"
                            x1="40"
                            y1="70"
                            x2="160"
                            y2="155"
                            gradientUnits="userSpaceOnUse"
                        >
                            <stop stop-color="#5a2e22" />
                            <stop offset="0.4" stop-color="#3d1c14" />
                            <stop offset="1" stop-color="#24100c" />
                        </linearGradient>
                        <linearGradient
                            id="mk-urushi-edge"
                            x1="48"
                            y1="118"
                            x2="152"
                            y2="158"
                            gradientUnits="userSpaceOnUse"
                        >
                            <stop stop-color="#7a4030" />
                            <stop offset="1" stop-color="#2c1410" />
                        </linearGradient>
                        <linearGradient
                            id="mk-inner"
                            x1="100"
                            y1="78"
                            x2="100"
                            y2="130"
                            gradientUnits="userSpaceOnUse"
                        >
                            <stop stop-color="#2a1510" />
                            <stop offset="1" stop-color="#120906" />
                        </linearGradient>
                        <linearGradient
                            id="mk-lid"
                            x1="50"
                            y1="24"
                            x2="150"
                            y2="72"
                            gradientUnits="userSpaceOnUse"
                        >
                            <stop stop-color="#6b3426" />
                            <stop offset="1" stop-color="#2e1610" />
                        </linearGradient>
                        <!-- 金蒔絵寄りの縁 -->
                        <linearGradient id="mk-kin" x1="0" y1="0" x2="1" y2="1">
                            <stop stop-color="#f0d78a" />
                            <stop offset="0.5" stop-color="#c9a24a" />
                            <stop offset="1" stop-color="#8a6820" />
                        </linearGradient>
                        <radialGradient id="mk-koban" cx="36%" cy="30%" r="70%">
                            <stop stop-color="#fff1c2" />
                            <stop offset="0.4" stop-color="#efc85a" />
                            <stop offset="0.78" stop-color="#c9942e" />
                            <stop offset="1" stop-color="#8f6418" />
                        </radialGradient>
                        <!-- 青海波 -->
                        <pattern
                            id="mk-seigaiha"
                            width="18"
                            height="10"
                            patternUnits="userSpaceOnUse"
                        >
                            <path
                                d="M0 10 Q4.5 2 9 10 Q13.5 2 18 10"
                                fill="none"
                                stroke="#5c6b7a"
                                stroke-opacity="0.12"
                                stroke-width="1"
                            />
                        </pattern>
                        <filter
                            id="mk-soft"
                            x="-25%"
                            y="-25%"
                            width="150%"
                            height="150%"
                        >
                            <feDropShadow
                                dx="0"
                                dy="2"
                                stdDeviation="1.8"
                                flood-color="#1a0c08"
                                flood-opacity="0.28"
                            />
                        </filter>
                    </defs>

                    <!-- 背景：和紙＋青海波 -->
                    <rect width="200" height="176" fill="url(#mk-washi)" />
                    <rect width="200" height="176" fill="url(#mk-seigaiha)" />
                    <!-- 薄い朝の光 -->
                    <ellipse
                        cx="72"
                        cy="48"
                        rx="70"
                        ry="40"
                        fill="#fff8e8"
                        opacity="0.45"
                    />

                    <!-- 床影 -->
                    <ellipse
                        cx="100"
                        cy="160"
                        rx="52"
                        ry="6"
                        fill="#2a1810"
                        opacity="0.14"
                    />

                    <!-- 蓋（開） -->
                    <g class="money-margin-koban__lid" filter="url(#mk-soft)">
                        <path
                            d="M54 66 L80 24 L150 36 L130 74 Z"
                            fill="url(#mk-lid)"
                            stroke="#c9a24a"
                            stroke-opacity="0.55"
                            stroke-width="1.2"
                        />
                        <!-- 蓋の金線 -->
                        <path
                            d="M82 30 L144 40"
                            stroke="#f0d78a"
                            stroke-opacity="0.35"
                            stroke-width="0.8"
                        />
                        <path
                            d="M78 42 L136 52"
                            stroke="#f0d78a"
                            stroke-opacity="0.2"
                            stroke-width="0.7"
                        />
                        <!-- つまみ -->
                        <rect
                            x="98"
                            y="42"
                            width="16"
                            height="5"
                            rx="1"
                            transform="rotate(-16 106 44)"
                            fill="url(#mk-kin)"
                        />
                    </g>

                    <!-- 箱本体（内側） -->
                    <g class="money-margin-koban__box">
                        <path
                            d="M46 80 L70 64 L130 64 L154 80 L154 122 L46 122 Z"
                            fill="url(#mk-inner)"
                        />
                        <path
                            d="M46 80 L46 140 L62 152 L62 90 Z"
                            fill="#4a261c"
                        />
                        <path
                            d="M154 80 L154 140 L138 152 L138 90 Z"
                            fill="#24110c"
                        />
                        <path
                            d="M62 90 L138 90 L138 152 L62 152 Z"
                            fill="url(#mk-inner)"
                        />
                        <!-- 内側の薄いきらめき -->
                        <path
                            d="M70 102 H130 M74 116 H126 M80 130 H120"
                            stroke="#c9a24a"
                            stroke-opacity="0.08"
                            stroke-width="1"
                            stroke-linecap="round"
                        />
                        <!-- 口縁の金ライン -->
                        <path
                            d="M48 80 L70 64 L130 64 L152 80"
                            fill="none"
                            stroke="url(#mk-kin)"
                            stroke-opacity="0.65"
                            stroke-width="1.4"
                        />
                    </g>

                    <!-- 中の小判 -->
                    <g class="money-margin-koban__coins">
                        <template v-if="mood === 'incomplete'">
                            <ellipse
                                cx="100"
                                cy="116"
                                rx="24"
                                ry="15"
                                fill="none"
                                stroke="#c9a24a"
                                stroke-width="1.4"
                                stroke-dasharray="3 3"
                                opacity="0.4"
                            />
                            <text
                                x="100"
                                y="120"
                                text-anchor="middle"
                                font-size="9"
                                fill="#8a7a60"
                                font-family="'Shippori Mincho', 'Noto Serif JP', serif"
                                opacity="0.55"
                            >
                                空
                            </text>
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
                                    :style="{
                                        animationDelay: `${index * 90}ms`,
                                    }"
                                >
                                    <ellipse
                                        cx="0"
                                        cy="1.5"
                                        rx="21"
                                        ry="13.5"
                                        fill="#6a4818"
                                        opacity="0.35"
                                    />
                                    <ellipse
                                        cx="0"
                                        cy="0"
                                        rx="21"
                                        ry="13.5"
                                        fill="url(#mk-koban)"
                                        stroke="#8a6820"
                                        stroke-width="1.1"
                                    />
                                    <ellipse
                                        cx="0"
                                        cy="0"
                                        rx="14"
                                        ry="8.5"
                                        fill="none"
                                        stroke="#fff6d0"
                                        stroke-opacity="0.5"
                                        stroke-width="1"
                                    />
                                    <text
                                        x="0"
                                        y="3.5"
                                        text-anchor="middle"
                                        font-size="10"
                                        font-weight="700"
                                        fill="#6b4814"
                                        font-family="'Shippori Mincho', 'Noto Serif JP', serif"
                                    >
                                        両
                                    </text>
                                </g>
                            </g>
                        </template>
                    </g>

                    <!-- 前縁（漆＋金縁） -->
                    <g class="money-margin-koban__front">
                        <path
                            d="M46 122 L62 152 L138 152 L154 122 L154 140 L138 162 L62 162 L46 140 Z"
                            fill="url(#mk-urushi-edge)"
                            stroke="#c9a24a"
                            stroke-opacity="0.45"
                            stroke-width="1.1"
                        />
                        <path
                            d="M54 132 H146"
                            stroke="#f0d78a"
                            stroke-opacity="0.22"
                            stroke-width="1.2"
                            stroke-linecap="round"
                        />
                    </g>

                    <!-- 左下の小さな家紋風装飾（装飾のみ） -->
                    <g opacity="0.22" transform="translate(18 148)">
                        <circle
                            cx="0"
                            cy="0"
                            r="7"
                            fill="none"
                            stroke="#5c6b7a"
                            stroke-width="0.9"
                        />
                        <circle
                            cx="0"
                            cy="0"
                            r="3.2"
                            fill="none"
                            stroke="#5c6b7a"
                            stroke-width="0.8"
                        />
                    </g>
                </svg>
            </div>

            <div
                class="money-margin-koban__badge pointer-events-none absolute top-3 right-3 rounded-sm border px-2.5 py-1 text-center shadow-sm backdrop-blur-sm"
                :class="{
                    'border-[#c9b896]/80 bg-[#fffaf0]/92 text-[#6b5c48]':
                        mood === 'incomplete',
                    'border-destructive/35 bg-[#fffaf0]/92 text-destructive':
                        mood === 'shortfall',
                    'border-[#c9a24a]/55 bg-[#fffaf0]/92 text-[#8a6820]':
                        mood === 'safe',
                }"
            >
                <p
                    class="text-[10px] leading-none font-medium tracking-wider opacity-80"
                >
                    箱の中
                </p>
                <p
                    class="mt-0.5 font-serif text-sm leading-none font-semibold tabular-nums"
                >
                    {{ coinCountLabel }}
                </p>
            </div>
        </div>

        <figcaption
            class="max-w-sm text-center font-serif text-sm leading-relaxed text-muted-foreground"
        >
            {{ resolvedCaption }}
        </figcaption>
    </figure>
</template>

<style scoped>
.money-margin-koban__lid {
    transform-origin: 100px 70px;
    animation: mk-lid-ease 5s ease-in-out infinite;
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
        transform: translateY(1.2px);
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
