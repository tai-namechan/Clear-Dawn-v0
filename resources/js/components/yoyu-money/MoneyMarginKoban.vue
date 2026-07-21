<script setup lang="ts">
import { computed } from 'vue';
import marginKoban0 from '@/assets/yoyu/money/margin-koban-0.webp';
import marginKoban1 from '@/assets/yoyu/money/margin-koban-1.webp';
import marginKoban3 from '@/assets/yoyu/money/margin-koban-3.webp';
import marginKoban5 from '@/assets/yoyu/money/margin-koban-5.webp';

type Mood = 'incomplete' | 'shortfall' | 'safe';
type CoinKey = 0 | 1 | 3 | 5;

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

const assetByCount: Record<CoinKey, string> = {
    0: marginKoban0,
    1: marginKoban1,
    3: marginKoban3,
    5: marginKoban5,
};

/** 箱の中に入る小判の枚数（アセット: 0 / 1 / 3 / 5） */
const coinCount = computed((): CoinKey => {
    if (props.mood === 'incomplete') {
        return 0;
    }

    if (props.mood === 'shortfall') {
        return 1;
    }

    // safe: 相対量で 3枚（控えめ）か 5枚（厚みあり）
    return props.level >= 0.65 ? 5 : 3;
});

const imageSrc = computed(() => assetByCount[coinCount.value]);

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

    if (coinCount.value >= 5) {
        return '箱に小判が5枚。余裕に厚みがあります。';
    }

    return '箱に小判が3枚。いまの余裕です。';
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

const imageAlt = computed(() => {
    if (coinCount.value === 0) {
        return '空の小判箱';
    }

    return `小判が${coinCount.value}枚入った小判箱`;
});
</script>

<template>
    <figure
        class="money-margin-koban flex flex-col items-center gap-3"
        role="img"
        :aria-label="`${statusLabel}。${imageAlt}`"
    >
        <div class="relative w-full max-w-[300px]">
            <div
                class="money-margin-koban__stage overflow-hidden rounded-xl border border-[#d8cfc0]/70 bg-[#f7f2e8] shadow-sm"
            >
                <img
                    :key="coinCount"
                    class="money-margin-koban__art h-auto w-full object-cover"
                    :src="imageSrc"
                    :alt="imageAlt"
                    width="1024"
                    height="1024"
                    decoding="async"
                />
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
.money-margin-koban__art {
    animation: mk-fade 0.45s ease-out both;
}

@media (prefers-reduced-motion: reduce) {
    .money-margin-koban__art {
        animation: none;
    }
}

@keyframes mk-fade {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}
</style>
