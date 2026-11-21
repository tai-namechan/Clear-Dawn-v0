<script setup lang="ts">
import { Sparkles } from '@lucide/vue';
import type { Component } from 'vue';

type DeltaTone = 'good' | 'bad' | 'neutral';

interface DeltaInfo {
    text: string;
    tone: DeltaTone;
}

interface StatusCard {
    key: string;
    label: string;
    unit: string;
    display: string;
    delta: DeltaInfo | null;
    icon: Component;
    showUnit: boolean;
}

interface Overall {
    score: number | null;
    display: string;
    delta: DeltaInfo | null;
}

defineProps<{
    overall: Overall;
    statusCards: StatusCard[];
}>();

function toneClass(tone: DeltaTone): string {
    if (tone === 'good') {
        return 'text-cd-moss';
    }

    if (tone === 'bad') {
        return 'text-cd-danger';
    }

    return 'text-cd-ink-muted';
}
</script>

<template>
    <div aria-label="今日の状態">
        <div class="mb-3 flex items-center justify-between gap-2">
            <h2 class="font-sans text-base font-bold text-cd-ink">
                今日の状態
            </h2>
            <p
                v-if="overall.score !== null"
                class="inline-flex items-center gap-1.5 font-sans text-xs text-cd-ink-muted"
            >
                <Sparkles
                    :size="14"
                    :stroke-width="1.6"
                    class="text-cd-icon-primary"
                />
                総合 {{ overall.display }} / 100
                <span
                    v-if="overall.delta"
                    :class="toneClass(overall.delta.tone)"
                >
                    {{ overall.delta.text }}
                </span>
            </p>
        </div>

        <!-- Same compact tiles as the performance screen -->
        <ul class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <li
                v-for="card in statusCards"
                :key="card.key"
                class="cd-condition-card rounded-2xl border border-[#efecf5] bg-white"
            >
                <span class="cd-condition-icon" aria-hidden="true">
                    <component :is="card.icon" :size="18" :stroke-width="1.7" />
                </span>
                <span class="min-w-0 flex-1">
                    <span
                        class="block font-sans text-xs font-bold text-cd-ink"
                        >{{ card.label }}</span
                    >
                    <span
                        class="mt-0.5 block font-sans text-[0.98rem] font-bold whitespace-nowrap text-cd-ink tabular-nums sm:text-lg"
                    >
                        {{ card.display }}
                        <small
                            v-if="card.showUnit"
                            class="text-xs font-semibold text-cd-ink-muted"
                            >{{ card.unit }}</small
                        >
                    </span>
                    <span
                        v-if="card.delta"
                        class="block font-sans text-[10px] font-medium whitespace-nowrap"
                        :class="toneClass(card.delta.tone)"
                        >{{ card.delta.text }}</span
                    >
                </span>
            </li>
        </ul>
    </div>
</template>
