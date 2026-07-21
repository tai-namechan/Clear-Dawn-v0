<script setup lang="ts">
import { formatYen } from '@/lib/yoyuMoney/format';

interface Props {
    label: string;
    amountMinor: string | null | undefined;
    hint?: string | null;
    emphasis?: 'default' | 'hero' | 'muted';
    unsetLabel?: string;
}

withDefaults(defineProps<Props>(), {
    hint: null,
    emphasis: 'default',
    unsetLabel: '未設定',
});
</script>

<template>
    <div
        class="rounded-xl border border-os-line bg-white px-3.5 py-3"
        :class="emphasis === 'hero' ? 'sm:px-5 sm:py-4' : ''"
    >
        <p class="text-[12px] font-semibold text-os-sub">{{ label }}</p>
        <p
            v-if="amountMinor === null || amountMinor === undefined"
            class="mt-1 font-bold text-os-faint"
            :class="emphasis === 'hero' ? 'text-2xl md:text-3xl' : 'text-lg'"
        >
            {{ unsetLabel }}
        </p>
        <p
            v-else
            class="mt-1 text-right font-bold tracking-tight text-os-ink tabular-nums"
            :class="
                emphasis === 'hero'
                    ? 'text-3xl text-os-yoyu md:text-4xl'
                    : emphasis === 'muted'
                      ? 'text-base text-os-sub'
                      : 'text-lg'
            "
        >
            {{ formatYen(amountMinor) }}
        </p>
        <p v-if="hint" class="mt-1 text-[12px] text-os-faint">
            {{ hint }}
        </p>
    </div>
</template>
