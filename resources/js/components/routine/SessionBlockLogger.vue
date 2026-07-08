<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    formatBlockLog,
    formatLoadTarget,
    formatAmountTarget,
} from '@/lib/routineConstants';
import type { RoutineBlockLog, TrackingType } from '@/types/routine';

interface Props {
    trackingType: TrackingType;
    targetBlocks: number;
    completedLogs: RoutineBlockLog[];
    loadUnit?: string | null;
    amountUnit?: string | null;
    defaultLoad?: string | null;
    defaultAmount?: string | null;
    logging?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    logging: false,
    loadUnit: 'kg',
    amountUnit: '回',
    defaultLoad: null,
    defaultAmount: null,
});

const emit = defineEmits<{
    log: [payload: {
        load_value?: string | null;
        amount_value?: number | null;
        amount_unit?: string | null;
        load_unit?: string | null;
    }];
}>();

const blockLoad = ref('');
const blockAmount = ref('');

const nextBlockNumber = computed(
    () => props.completedLogs.length + 1,
);

const allBlocksLogged = computed(
    () =>
        props.targetBlocks > 0 &&
        props.completedLogs.length >= props.targetBlocks,
);

const showLoad = computed(() => props.trackingType === 'weight_reps');
const showAmount = computed(
    () =>
        props.trackingType === 'weight_reps' ||
        props.trackingType === 'reps' ||
        props.trackingType === 'count' ||
        props.trackingType === 'duration' ||
        props.trackingType === 'distance',
);

watch(
    () => [props.defaultLoad, props.defaultAmount, props.completedLogs.length],
    () => {
        blockLoad.value = props.defaultLoad ?? '';
        blockAmount.value = props.defaultAmount ?? '';
    },
    { immediate: true },
);

function submitBlock(): void {
    const payload: {
        load_value?: string | null;
        amount_value?: number | null;
        amount_unit?: string | null;
        load_unit?: string | null;
    } = {};

    if (showLoad.value) {
        payload.load_value = blockLoad.value || null;
        payload.load_unit = props.loadUnit;
    }

    if (showAmount.value) {
        payload.amount_value = blockAmount.value
            ? Number(blockAmount.value)
            : null;
        payload.amount_unit = props.amountUnit;
    }

    emit('log', payload);
}
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center justify-between gap-2">
            <p class="font-sans text-xs tracking-[0.08em] text-cd-ink-muted">
                セット完了
                <span v-if="targetBlocks">
                    {{ completedLogs.length }} / {{ targetBlocks }}
                </span>
            </p>
            <div
                v-if="targetBlocks"
                class="h-1.5 flex-1 max-w-32 overflow-hidden rounded-full bg-muted"
            >
                <div
                    class="h-full bg-primary transition-all"
                    :style="{
                        width: `${Math.min(100, (completedLogs.length / targetBlocks) * 100)}%`,
                    }"
                />
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-cd-line/60">
            <table class="w-full min-w-[420px] text-left font-sans text-sm">
                <thead>
                    <tr
                        class="border-b border-cd-line/60 bg-white/40 text-xs tracking-[0.06em] text-cd-ink-muted"
                    >
                        <th class="px-3 py-2 font-medium">セット</th>
                        <th v-if="showLoad" class="px-3 py-2 font-medium">
                            重量
                        </th>
                        <th v-if="showAmount" class="px-3 py-2 font-medium">
                            量
                        </th>
                        <th class="px-3 py-2 font-medium">状態</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="log in completedLogs"
                        :key="log.id"
                        class="border-b border-cd-line/40 bg-cd-moss/5"
                    >
                        <td class="px-3 py-2 text-cd-ink-muted">
                            {{ log.block_number }}
                        </td>
                        <td v-if="showLoad" class="px-3 py-2 text-cd-ink">
                            {{
                                formatLoadTarget(
                                    log.load_value,
                                    log.load_unit,
                                ) ?? '—'
                            }}
                        </td>
                        <td v-if="showAmount" class="px-3 py-2 text-cd-ink">
                            {{
                                formatAmountTarget(
                                    log.amount_value,
                                    log.amount_unit,
                                ) ?? '—'
                            }}
                        </td>
                        <td class="px-3 py-2 font-sans text-xs text-cd-moss">
                            完了
                        </td>
                    </tr>

                    <tr
                        v-if="!allBlocksLogged && trackingType !== 'check'"
                        class="border-b border-cd-line/40 last:border-b-0"
                    >
                        <td class="px-3 py-2 text-primary">
                            {{ nextBlockNumber }}
                        </td>
                        <td v-if="showLoad" class="px-3 py-2">
                            <Input
                                v-model="blockLoad"
                                type="number"
                                step="0.5"
                                class="h-8"
                                :disabled="logging"
                            />
                        </td>
                        <td v-if="showAmount" class="px-3 py-2">
                            <Input
                                v-model="blockAmount"
                                type="number"
                                step="0.1"
                                class="h-8"
                                :disabled="logging"
                            />
                        </td>
                        <td class="px-3 py-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                :disabled="logging"
                                @click="submitBlock"
                            >
                                記録
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ul
            v-if="completedLogs.length"
            class="space-y-1 font-sans text-xs text-cd-ink-muted"
        >
            <li v-for="log in completedLogs" :key="`summary-${log.id}`">
                セット {{ log.block_number }}: {{ formatBlockLog(log) }}
            </li>
        </ul>
    </div>
</template>
