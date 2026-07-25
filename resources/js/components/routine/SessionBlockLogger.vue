<script setup lang="ts">
import { ClipboardList } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import {
    formatAmountTarget,
    formatLoadTarget,
    formatQuantityDisplay,
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
    log: [
        payload: {
            load_value?: string | null;
            amount_value?: number | null;
            amount_unit?: string | null;
            load_unit?: string | null;
        },
    ];
    unlog: [blockLogId: string];
}>();

const draftLoad = ref('');
const draftAmount = ref('');

const showLoad = computed(() => props.trackingType === 'weight_reps');
const showAmount = computed(
    () =>
        props.trackingType === 'weight_reps' ||
        props.trackingType === 'reps' ||
        props.trackingType === 'count' ||
        props.trackingType === 'duration' ||
        props.trackingType === 'distance',
);
const amountStep = computed(() =>
    props.trackingType === 'duration' || props.trackingType === 'distance'
        ? '0.1'
        : '1',
);
const amountHeader = computed(() => {
    if (props.trackingType === 'duration') {
        return '時間';
    }

    if (props.trackingType === 'distance') {
        return '距離';
    }

    return '回数';
});

const totalBlocks = computed(() => Math.max(props.targetBlocks, 1));

const nextBlockNumber = computed(() => props.completedLogs.length + 1);

const allBlocksLogged = computed(
    () =>
        props.targetBlocks > 0 &&
        props.completedLogs.length >= props.targetBlocks,
);

const logsByNumber = computed(() => {
    const map = new Map<number, RoutineBlockLog>();

    for (const log of props.completedLogs) {
        map.set(log.block_number, log);
    }

    return map;
});

watch(
    () => [props.defaultLoad, props.defaultAmount, props.completedLogs.length],
    () => {
        draftLoad.value = formatQuantityDisplay(props.defaultLoad) ?? '';
        draftAmount.value = formatQuantityDisplay(props.defaultAmount) ?? '';
    },
    { immediate: true },
);

function submitActiveBlock(): void {
    if (props.logging || allBlocksLogged.value || props.trackingType === 'check') {
        return;
    }

    const payload: {
        load_value?: string | null;
        amount_value?: number | null;
        amount_unit?: string | null;
        load_unit?: string | null;
    } = {};

    if (showLoad.value) {
        payload.load_value = draftLoad.value || null;
        payload.load_unit = props.loadUnit;
    }

    if (showAmount.value) {
        payload.amount_value = draftAmount.value
            ? Number(draftAmount.value)
            : null;
        payload.amount_unit = props.amountUnit;
    }

    emit('log', payload);
}

function unlogBlock(blockNumber: number): void {
    if (props.logging) {
        return;
    }

    const log = logsByNumber.value.get(blockNumber);

    if (!log) {
        return;
    }

    emit('unlog', log.id);
}

function rowState(blockNumber: number): 'done' | 'active' | 'upcoming' {
    if (logsByNumber.value.has(blockNumber)) {
        return 'done';
    }

    if (blockNumber === nextBlockNumber.value && !allBlocksLogged.value) {
        return 'active';
    }

    return 'upcoming';
}
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <ClipboardList
                    :size="16"
                    :stroke-width="1.6"
                    class="text-cd-ink-muted"
                />
                <p class="font-sans text-sm font-medium text-cd-ink">
                    セット記録
                </p>
            </div>
            <p class="font-sans text-xs text-cd-ink-muted">
                セット完了 {{ completedLogs.length }} /
                {{ targetBlocks || totalBlocks }}
            </p>
        </div>

        <div
            v-if="trackingType === 'check'"
            class="rounded-xl border border-cd-line/60 bg-white/40 px-4 py-3 font-sans text-sm text-cd-ink-muted"
        >
            チェック項目です。「完了して次へ」で進めます。
        </div>

        <div
            v-else
            class="overflow-x-auto rounded-xl border border-cd-line/60"
        >
            <table class="w-full min-w-[420px] text-left font-sans text-sm">
                <thead>
                    <tr
                        class="border-b border-cd-line/60 bg-white/40 text-xs tracking-[0.06em] text-cd-ink-muted"
                    >
                        <th class="px-3 py-2.5 font-medium">セット</th>
                        <th v-if="showLoad" class="px-3 py-2.5 font-medium">
                            重量
                        </th>
                        <th v-if="showAmount" class="px-3 py-2.5 font-medium">
                            {{ amountHeader }}
                        </th>
                        <th class="w-16 px-3 py-2.5 text-center font-medium">
                            状態
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="blockNumber in totalBlocks"
                        :key="blockNumber"
                        class="border-b border-cd-line/40 last:border-b-0"
                        :class="
                            rowState(blockNumber) === 'done'
                                ? 'bg-cd-moss/5'
                                : ''
                        "
                    >
                        <td class="px-3 py-2.5 text-cd-ink-muted">
                            {{ blockNumber }}セット目
                        </td>

                        <template v-if="rowState(blockNumber) === 'done'">
                            <td
                                v-if="showLoad"
                                class="px-3 py-2.5 text-cd-ink"
                            >
                                {{
                                    formatLoadTarget(
                                        logsByNumber.get(blockNumber)
                                            ?.load_value,
                                        logsByNumber.get(blockNumber)
                                            ?.load_unit,
                                    ) ?? '—'
                                }}
                            </td>
                            <td
                                v-if="showAmount"
                                class="px-3 py-2.5 text-cd-ink"
                            >
                                {{
                                    formatAmountTarget(
                                        logsByNumber.get(blockNumber)
                                            ?.amount_value,
                                        logsByNumber.get(blockNumber)
                                            ?.amount_unit,
                                    ) ?? '—'
                                }}
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <button
                                    type="button"
                                    class="inline-flex size-5 items-center justify-center rounded-full bg-primary text-[10px] font-semibold text-primary-foreground transition-opacity hover:opacity-80 disabled:opacity-50"
                                    :disabled="logging"
                                    aria-label="このセットの記録を取り消す"
                                    title="チェックを外す"
                                    @click="unlogBlock(blockNumber)"
                                >
                                    ✓
                                </button>
                            </td>
                        </template>

                        <template
                            v-else-if="rowState(blockNumber) === 'active'"
                        >
                            <td v-if="showLoad" class="px-3 py-2.5">
                                <div class="flex items-center gap-1.5">
                                    <Input
                                        v-model="draftLoad"
                                        type="number"
                                        step="0.5"
                                        class="h-8"
                                        placeholder="重量を入力"
                                        :disabled="logging"
                                        @keydown.enter.prevent="
                                            submitActiveBlock
                                        "
                                    />
                                    <span
                                        class="shrink-0 font-sans text-xs text-cd-ink-muted"
                                    >
                                        {{ loadUnit ?? 'kg' }}
                                    </span>
                                </div>
                            </td>
                            <td v-if="showAmount" class="px-3 py-2.5">
                                <div class="flex items-center gap-1.5">
                                    <Input
                                        v-model="draftAmount"
                                        type="number"
                                        :step="amountStep"
                                        class="h-8"
                                        :disabled="logging"
                                        @keydown.enter.prevent="
                                            submitActiveBlock
                                        "
                                    />
                                    <span
                                        class="shrink-0 font-sans text-xs text-cd-ink-muted"
                                    >
                                        {{ amountUnit ?? '回' }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <button
                                    type="button"
                                    class="inline-flex size-5 items-center justify-center rounded-full border-2 border-cd-line transition-colors hover:border-primary hover:bg-primary/10 disabled:opacity-50"
                                    :disabled="logging"
                                    aria-label="このセットを記録"
                                    @click="submitActiveBlock"
                                />
                            </td>
                        </template>

                        <template v-else>
                            <td v-if="showLoad" class="px-3 py-2.5">
                                <div class="flex items-center gap-1.5">
                                    <Input
                                        type="number"
                                        class="h-8"
                                        placeholder="重量を入力"
                                        disabled
                                    />
                                    <span
                                        class="shrink-0 font-sans text-xs text-cd-ink-muted"
                                    >
                                        {{ loadUnit ?? 'kg' }}
                                    </span>
                                </div>
                            </td>
                            <td v-if="showAmount" class="px-3 py-2.5">
                                <div class="flex items-center gap-1.5">
                                    <Input
                                        type="number"
                                        class="h-8"
                                        :model-value="
                                            formatQuantityDisplay(
                                                defaultAmount,
                                            ) ?? ''
                                        "
                                        disabled
                                    />
                                    <span
                                        class="shrink-0 font-sans text-xs text-cd-ink-muted"
                                    >
                                        {{ amountUnit ?? '回' }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <span
                                    class="inline-flex size-5 rounded-full border-2 border-cd-line/70"
                                    aria-hidden="true"
                                />
                            </td>
                        </template>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
