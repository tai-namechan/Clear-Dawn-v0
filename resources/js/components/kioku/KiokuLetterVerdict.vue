<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    KIOKU_LETTER_SENSITIVE_VERDICT,
    KIOKU_LETTER_VERDICTS,
} from '@/lib/kiokuLetter.mjs';
import { verdict as verdictRoute } from '@/routes/kioku/letters/items';
import type { KiokuLetterItem } from '@/types/kiokuLetter';

const props = defineProps<{
    letterId: string;
    item: KiokuLetterItem;
    disabled: boolean;
}>();

const saving = ref(false);

function submitVerdict(value: string): void {
    if (props.disabled || saving.value) {
        return;
    }

    if (value === KIOKU_LETTER_SENSITIVE_VERDICT.value) {
        const confirmed = window.confirm(
            'この記憶を「表示すべきでない記憶」として記録しますか？手紙の生成は停止されます。',
        );

        if (!confirmed) {
            return;
        }
    }

    saving.value = true;
    router.put(
        verdictRoute.url({ letter: props.letterId, letterItem: props.item.id }),
        { verdict: value },
        {
            preserveScroll: true,
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}
</script>

<template>
    <div>
        <div
            class="flex flex-wrap gap-1.5"
            role="group"
            aria-label="この記憶の判定"
        >
            <button
                v-for="option in KIOKU_LETTER_VERDICTS"
                :key="option.value"
                type="button"
                class="rounded-full border px-3 py-1.5 text-[11.5px] font-bold transition-colors focus-visible:ring-2 focus-visible:ring-(--letter-accent)/40 disabled:cursor-default disabled:opacity-60 motion-reduce:transition-none"
                :class="
                    item.verdict === option.value
                        ? 'border-(--letter-accent) bg-(--letter-accent) text-white'
                        : 'border-os-line bg-os-kioku-paper text-os-sub hover:border-(--letter-accent)/40'
                "
                :disabled="disabled || saving"
                :aria-pressed="item.verdict === option.value"
                :title="option.description"
                @click="submitVerdict(option.value)"
            >
                {{ option.label }}
                <span class="ml-1 font-normal">{{ option.description }}</span>
            </button>
        </div>
        <div class="mt-2">
            <button
                type="button"
                class="text-[11px] text-os-faint underline decoration-dotted underline-offset-2 transition-colors hover:text-[#C05A48] disabled:cursor-default disabled:opacity-60 motion-reduce:transition-none"
                :class="
                    item.verdict === KIOKU_LETTER_SENSITIVE_VERDICT.value
                        ? 'font-bold text-[#C05A48]'
                        : ''
                "
                :disabled="disabled || saving"
                :aria-pressed="
                    item.verdict === KIOKU_LETTER_SENSITIVE_VERDICT.value
                "
                @click="submitVerdict(KIOKU_LETTER_SENSITIVE_VERDICT.value)"
            >
                {{ KIOKU_LETTER_SENSITIVE_VERDICT.label }}
            </button>
        </div>
    </div>
</template>
