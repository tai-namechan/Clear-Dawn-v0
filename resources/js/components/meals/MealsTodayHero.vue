<script setup lang="ts">
import { Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

interface Remaining {
    kcal: number;
    protein_g: number;
    fat_g: number;
    carb_g: number;
}

interface GoalAmounts {
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
    goalAmounts?: GoalAmounts | null;
}

const props = withDefaults(defineProps<Props>(), {
    goalAmounts: null,
});

const emit = defineEmits<{
    'set-goal': [];
}>();

function formatNum(value: number | string): string {
    return Number(value).toLocaleString('ja-JP');
}

const ringPercent = computed(() => {
    if (props.kcalAchievement == null) {
        return 0;
    }

    return Math.min(100, Math.max(0, props.kcalAchievement));
});

const ringStyle = computed(() => ({
    background: `conic-gradient(var(--cd-primary) ${ringPercent.value}%, #E8E4F0 ${ringPercent.value}%)`,
}));
</script>

<template>
    <div
        class="grid gap-4 lg:grid-cols-2"
        aria-label="残りの摂取目安"
    >
        <section
            class="rounded-2xl border border-cd-line bg-white p-5 shadow-sm"
        >
            <p class="font-sans text-xs font-medium text-cd-ink-muted">
                残りの摂取目安
            </p>

            <template v-if="remaining">
                <div class="mt-4 flex items-center gap-5">
                    <div
                        class="flex size-[5.5rem] shrink-0 items-center justify-center rounded-full p-[6px]"
                        :style="ringStyle"
                        role="img"
                        :aria-label="`達成率 ${ringPercent}%`"
                    >
                        <div
                            class="flex size-full flex-col items-center justify-center rounded-full bg-white"
                        >
                            <span
                                class="font-sans text-lg font-bold leading-none text-cd-ink"
                            >
                                {{ ringPercent }}%
                            </span>
                            <span
                                class="mt-1 font-sans text-[10px] text-cd-ink-muted"
                            >
                                達成率
                            </span>
                        </div>
                    </div>

                    <div class="min-w-0">
                        <p
                            class="font-sans text-2xl font-bold tracking-tight text-cd-ink md:text-3xl"
                        >
                            あと {{ formatNum(remaining.kcal) }}
                            <span
                                class="text-base font-semibold text-cd-ink-muted"
                                >kcal</span
                            >
                        </p>
                        <p class="mt-2 font-sans text-sm text-cd-ink-muted">
                            目標達成に向けて、あとこれだけ摂れます。
                        </p>
                        <p
                            v-if="goalKcal !== null"
                            class="mt-2 font-sans text-xs text-cd-ink-muted"
                        >
                            {{ formatNum(totalsKcal) }} /
                            {{ formatNum(goalKcal) }} kcal（{{
                                ringPercent
                            }}%）
                        </p>
                    </div>
                </div>
            </template>
            <template v-else>
                <p class="mt-3 font-sans text-xl font-semibold text-cd-ink">
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
        </section>

        <section
            class="rounded-2xl border border-cd-line bg-white p-5 shadow-sm"
        >
            <p class="font-sans text-xs font-medium text-cd-ink-muted">
                残りの PFC
            </p>
            <div v-if="remaining" class="mt-3 grid grid-cols-3 gap-2.5">
                <div
                    class="rounded-2xl bg-[#E8F6EE] px-3 py-3 text-center"
                >
                    <p class="font-sans text-xs font-semibold text-cd-pfc-p">
                        P
                    </p>
                    <p class="mt-1 font-sans text-lg font-bold text-cd-ink">
                        +{{ formatNum(remaining.protein_g) }}
                        <span class="text-xs font-medium">g</span>
                    </p>
                    <p
                        v-if="goalAmounts"
                        class="mt-1 font-sans text-[10px] text-cd-ink-muted"
                    >
                        目標 {{ formatNum(goalAmounts.protein_g) }} g
                    </p>
                </div>
                <div
                    class="rounded-2xl bg-[#FFF1E4] px-3 py-3 text-center"
                >
                    <p class="font-sans text-xs font-semibold text-cd-pfc-f">
                        F
                    </p>
                    <p class="mt-1 font-sans text-lg font-bold text-cd-ink">
                        +{{ formatNum(remaining.fat_g) }}
                        <span class="text-xs font-medium">g</span>
                    </p>
                    <p
                        v-if="goalAmounts"
                        class="mt-1 font-sans text-[10px] text-cd-ink-muted"
                    >
                        目標 {{ formatNum(goalAmounts.fat_g) }} g
                    </p>
                </div>
                <div
                    class="rounded-2xl bg-[#E7F3FC] px-3 py-3 text-center"
                >
                    <p class="font-sans text-xs font-semibold text-cd-pfc-c">
                        C
                    </p>
                    <p class="mt-1 font-sans text-lg font-bold text-cd-ink">
                        +{{ formatNum(remaining.carb_g) }}
                        <span class="text-xs font-medium">g</span>
                    </p>
                    <p
                        v-if="goalAmounts"
                        class="mt-1 font-sans text-[10px] text-cd-ink-muted"
                    >
                        目標 {{ formatNum(goalAmounts.carb_g) }} g
                    </p>
                </div>
            </div>
            <p
                class="mt-4 flex items-start gap-2 font-sans text-sm leading-relaxed text-cd-ink"
            >
                <Sparkles
                    :size="16"
                    :stroke-width="1.7"
                    class="mt-0.5 shrink-0 text-primary"
                />
                <span>
                    <span class="font-medium text-cd-ink"
                        >次に摂るとよいもの</span
                    >
                    <span class="mt-0.5 block text-cd-ink-muted">{{
                        nextFoodHint
                    }}</span>
                </span>
            </p>
        </section>
    </div>
</template>
