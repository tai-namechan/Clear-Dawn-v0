<script setup lang="ts">
import { formatSignedYen, formatYen, isNegativeMinor } from '@/lib/yoyuMoney/format';

interface Props {
    amountMinor: string | null | undefined;
    signed?: boolean;
    unsetLabel?: string;
}

withDefaults(defineProps<Props>(), {
    signed: false,
    unsetLabel: '未設定',
});
</script>

<template>
    <span
        class="inline-block tabular-nums"
        :class="
            amountMinor === null || amountMinor === undefined
                ? 'text-os-faint'
                : isNegativeMinor(amountMinor)
                  ? 'text-[#8A5A3B]'
                  : 'text-os-ink'
        "
    >
        <template v-if="amountMinor === null || amountMinor === undefined">
            {{ unsetLabel }}
        </template>
        <template v-else-if="signed">
            {{ formatSignedYen(amountMinor) }}
        </template>
        <template v-else>
            {{ formatYen(amountMinor) }}
        </template>
    </span>
</template>
