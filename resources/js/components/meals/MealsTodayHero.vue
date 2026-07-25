<script setup lang="ts">
import { Button } from '@/components/ui/button';

interface Remaining {
    kcal: number;
    protein_g: number;
    fat_g: number;
    carb_g: number;
}

interface Props {
    remaining: Remaining | null;
    totalsKcal: number;
    goalKcal: number | null;
    kcalAchievement: number | null;
    nextFoodHint: string;
}

defineProps<Props>();

const emit = defineEmits<{
    'set-goal': [];
}>();

function formatNum(value: number | string): string {
    return Number(value).toLocaleString('ja-JP');
}
</script>

<template>
    <div class="grid gap-5 lg:grid-cols-[1.2fr_1fr]" aria-label="残りの摂取目安">
        <div>
            <p class="font-sans text-xs font-medium text-cd-ink-muted">
                残りの摂取目安
            </p>
            <template v-if="remaining">
                <p
                    class="mt-2 font-sans text-3xl font-bold tracking-tight text-cd-ink"
                >
                    あと {{ formatNum(remaining.kcal) }}
                    <span class="text-lg font-semibold text-cd-ink-muted"
                        >kcal</span
                    >
                </p>
                <p class="mt-2 font-sans text-sm text-cd-ink-muted">
                    目標達成に向けて、あとこれだけ摂れます。
                </p>
                <p
                    v-if="kcalAchievement !== null && goalKcal !== null"
                    class="mt-1 font-sans text-xs text-cd-ink-muted"
                >
                    現在 {{ formatNum(totalsKcal) }} /
                    {{ formatNum(goalKcal) }} kcal（{{ kcalAchievement }}%）
                </p>
            </template>
            <template v-else>
                <p class="mt-2 font-sans text-xl font-semibold text-cd-ink">
                    目標未設定
                </p>
                <Button
                    type="button"
                    size="sm"
                    class="mt-3 font-sans"
                    @click="emit('set-goal')"
                >
                    目標を設定
                </Button>
            </template>
        </div>

        <div>
            <p class="font-sans text-xs font-medium text-cd-ink-muted">
                残りの PFC
            </p>
            <div v-if="remaining" class="mt-3 grid grid-cols-3 gap-2">
                <div class="rounded-xl bg-cd-pfc-p/15 px-3 py-3 text-center">
                    <p class="font-sans text-[11px] font-medium text-cd-pfc-p">
                        P
                    </p>
                    <p class="mt-1 font-sans text-lg font-bold text-cd-ink">
                        +{{ formatNum(remaining.protein_g) }}
                        <span class="text-xs font-medium">g</span>
                    </p>
                </div>
                <div class="rounded-xl bg-cd-pfc-f/15 px-3 py-3 text-center">
                    <p class="font-sans text-[11px] font-medium text-cd-pfc-f">
                        F
                    </p>
                    <p class="mt-1 font-sans text-lg font-bold text-cd-ink">
                        +{{ formatNum(remaining.fat_g) }}
                        <span class="text-xs font-medium">g</span>
                    </p>
                </div>
                <div class="rounded-xl bg-cd-pfc-c/15 px-3 py-3 text-center">
                    <p class="font-sans text-[11px] font-medium text-cd-pfc-c">
                        C
                    </p>
                    <p class="mt-1 font-sans text-lg font-bold text-cd-ink">
                        +{{ formatNum(remaining.carb_g) }}
                        <span class="text-xs font-medium">g</span>
                    </p>
                </div>
            </div>
            <p class="mt-3 font-sans text-sm leading-relaxed text-cd-ink">
                <span class="text-xs font-medium text-cd-ink-muted"
                    >次に摂るとよいもの</span
                ><br />
                {{ nextFoodHint }}
            </p>
        </div>
    </div>
</template>
