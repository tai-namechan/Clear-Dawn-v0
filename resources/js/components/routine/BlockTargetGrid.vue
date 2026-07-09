<script setup lang="ts">
import { computed, watch } from 'vue';
import { Input } from '@/components/ui/input';
import type { TrackingType } from '@/types/routine';

export type BlockTargetRow = {
    /** number input 経由で number になることがあるため両方許容 */
    load: string | number;
    amount: string | number;
    memo: string | number;
};

interface Props {
    trackingType: TrackingType;
    blockCount: number;
    loadUnit?: string | null;
    amountUnit?: string | null;
    disabled?: boolean;
    loadError?: string;
    amountError?: string;
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
    loadUnit: 'kg',
    amountUnit: '回',
    loadError: undefined,
    amountError: undefined,
});

const rows = defineModel<BlockTargetRow[]>('rows', { default: () => [] });

const showLoad = computed(() => props.trackingType === 'weight_reps');
const showAmount = computed(
    () =>
        props.trackingType === 'weight_reps' ||
        props.trackingType === 'reps' ||
        props.trackingType === 'count' ||
        props.trackingType === 'duration' ||
        props.trackingType === 'distance',
);
const showMemo = computed(() => props.trackingType !== 'check');

function emptyRow(): BlockTargetRow {
    return { load: '', amount: '', memo: '' };
}

function syncRows(count: number): void {
    const next = [...rows.value];

    while (next.length < count) {
        next.push(emptyRow());
    }

    while (next.length > count) {
        next.pop();
    }

    rows.value = next;
}

watch(
    () => props.blockCount,
    (count) => {
        syncRows(Math.max(count, 1));
    },
    { immediate: true },
);
</script>

<template>
    <div class="overflow-x-auto rounded-xl border border-cd-line/60">
        <table class="w-full min-w-[480px] text-left font-sans text-sm">
            <thead>
                <tr
                    class="border-b border-cd-line/60 bg-white/40 text-xs tracking-[0.06em] text-cd-ink-muted"
                >
                    <th class="px-3 py-2 font-medium">セット</th>
                    <th v-if="showLoad" class="px-3 py-2 font-medium">
                        重量 ({{ loadUnit ?? 'kg' }})
                    </th>
                    <th v-if="showAmount" class="px-3 py-2 font-medium">
                        {{
                            trackingType === 'duration'
                                ? `時間 (${amountUnit ?? '秒'})`
                                : trackingType === 'distance'
                                  ? `距離 (${amountUnit ?? 'km'})`
                                  : `回数 (${amountUnit ?? '回'})`
                        }}
                    </th>
                    <th v-if="showMemo" class="px-3 py-2 font-medium">メモ</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(row, index) in rows"
                    :key="index"
                    class="border-b border-cd-line/40 last:border-b-0"
                >
                    <td class="px-3 py-2 text-cd-ink-muted">
                        {{ index + 1 }}
                    </td>
                    <td v-if="showLoad" class="px-3 py-2">
                        <Input
                            v-model="row.load"
                            type="number"
                            step="0.5"
                            min="0"
                            class="h-8"
                            :disabled="disabled"
                            :aria-invalid="index === 0 && Boolean(loadError)"
                        />
                    </td>
                    <td v-if="showAmount" class="px-3 py-2">
                        <Input
                            v-model="row.amount"
                            type="number"
                            step="0.1"
                            min="0"
                            class="h-8"
                            :disabled="disabled"
                            :aria-invalid="index === 0 && Boolean(amountError)"
                        />
                    </td>
                    <td v-if="showMemo" class="px-3 py-2">
                        <Input
                            v-model="row.memo"
                            type="text"
                            class="h-8"
                            placeholder="任意"
                            :disabled="disabled"
                        />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
