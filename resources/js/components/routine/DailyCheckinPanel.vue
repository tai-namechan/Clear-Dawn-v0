<script setup lang="ts">
import { Button } from '@/components/ui/button';
import type { CheckinFormState } from '@/types/todayOps';

interface Props {
    modelValue: CheckinFormState;
    saving?: boolean;
    hasExisting?: boolean;
    compact?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    saving: false,
    hasExisting: false,
    compact: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: CheckinFormState];
    save: [];
}>();

const fields = [
    ['sleep_quality', '睡眠'],
    ['fatigue', '疲労'],
    ['muscle_soreness', '筋肉痛'],
    ['stress', 'ストレス'],
    ['mood', '気分'],
    ['readiness_self', '主観'],
] as const;

function updateField(key: keyof CheckinFormState, raw: string): void {
    const next = Math.min(10, Math.max(0, Number(raw) || 0));
    emit('update:modelValue', {
        ...props.modelValue,
        [key]: next,
    });
}
</script>

<template>
    <section
        class="rounded-2xl border border-cd-line/80 bg-cd-surface/95 px-5 py-4 shadow-sm"
        aria-label="30秒チェックイン"
    >
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="font-sans text-sm font-semibold text-cd-ink">
                    30秒チェックイン
                </h2>
                <p class="mt-1 font-sans text-xs text-muted-foreground">
                    0〜10 で直感的に入力（未入力なら作戦カードが促します）
                </p>
            </div>
            <Button
                type="button"
                size="sm"
                class="font-sans"
                :disabled="saving"
                @click="emit('save')"
            >
                {{ hasExisting ? '更新' : '記録' }}
            </Button>
        </div>

        <div
            class="mt-4 grid gap-3"
            :class="compact ? 'grid-cols-2 md:grid-cols-3' : 'sm:grid-cols-2 lg:grid-cols-3'"
        >
            <label
                v-for="field in fields"
                :key="field[0]"
                class="flex flex-col gap-1.5 font-sans text-xs text-muted-foreground"
            >
                <span class="flex items-center justify-between gap-2">
                    <span>{{ field[1] }}</span>
                    <span class="tabular-nums text-sm font-semibold text-cd-ink">
                        {{ modelValue[field[0]] }} / 10
                    </span>
                </span>
                <input
                    type="range"
                    min="0"
                    max="10"
                    step="1"
                    :value="modelValue[field[0]]"
                    class="h-2 w-full cursor-pointer accent-primary"
                    @input="
                        updateField(
                            field[0],
                            ($event.target as HTMLInputElement).value,
                        )
                    "
                />
            </label>
        </div>
    </section>
</template>
