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
            <h2 class="font-sans text-base font-semibold text-cd-ink">
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

        <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <li
                v-for="card in statusCards"
                :key="card.key"
                class="relative rounded-xl border border-cd-line bg-cd-surface p-4 pr-12"
            >
                <component
                    :is="card.icon"
                    class="pointer-events-none absolute top-[14px] right-4 text-cd-icon-primary opacity-90"
                    :size="18"
                    :stroke-width="1.6"
                />
                <p class="font-sans text-xs font-medium text-cd-ink-muted">
                    {{ card.label }}
                </p>
                <p class="mt-3 font-sans text-2xl font-semibold text-cd-ink">
                    {{ card.display }}
                    <span
                        v-if="card.showUnit"
                        class="text-sm font-medium text-cd-ink-muted"
                        >{{ card.unit }}</span
                    >
                </p>
                <p
                    v-if="card.delta"
                    class="mt-1.5 font-sans text-xs font-medium"
                    :class="toneClass(card.delta.tone)"
                >
                    {{ card.delta.text }}
                </p>
            </li>
        </ul>
    </div>
</template>
