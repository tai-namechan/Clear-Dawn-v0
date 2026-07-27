<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { UtensilsCrossed } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { NutritionGoal } from '@/types/routine';

interface Props {
    goal: NutritionGoal | null;
}

defineProps<Props>();

const emit = defineEmits<{
    'edit-goal': [];
}>();

function formatNum(value: number | string): string {
    return Number(value).toLocaleString('ja-JP');
}
</script>

<template>
    <div class="flex flex-col gap-5" aria-label="栄養目標">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="font-sans text-base font-semibold text-cd-ink">
                    栄養目標
                </h2>
                <p class="mt-1 font-sans text-sm text-cd-ink-muted">
                    1 日あたりの目標値。残り kcal / PFC の基準になります。
                </p>
                <p v-if="goal" class="mt-3 font-sans text-sm text-cd-ink">
                    現在: {{ formatNum(goal.kcal) }} kcal / P
                    {{ formatNum(goal.protein_g) }}g / F
                    {{ formatNum(goal.fat_g) }}g / C
                    {{ formatNum(goal.carb_g) }}g
                </p>
                <p v-else class="mt-3 font-sans text-sm text-cd-ink-muted">
                    まだ目標がありません。
                </p>
            </div>
            <Button type="button" class="font-sans" @click="emit('edit-goal')">
                目標を設定
            </Button>
        </div>
        <Link
            href="/meals/foods"
            class="inline-flex items-center gap-2 font-sans text-sm font-medium text-primary underline-offset-2 hover:underline"
        >
            <UtensilsCrossed :size="14" :stroke-width="1.6" />
            マイ食品を管理
        </Link>
    </div>
</template>
