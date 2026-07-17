<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { Button } from '@/components/ui/button';
import MoneySubnav from '@/components/yoyu-money/MoneySubnav.vue';
import { formatSignedYen, formatYen, minorToDisplayString } from '@/lib/yoyuMoney/format';
import type { MoneySimulationRow } from '@/lib/yoyuMoney/types';

interface Props {
    simulations: MoneySimulationRow[];
}

const props = defineProps<Props>();

const createForm = reactive({
    name: '',
    horizon_months: 3,
    memo: '',
});

function submitCreate(): void {
    router.post(
        '/yoyu/money/simulations',
        {
            name: createForm.name || null,
            horizon_months: createForm.horizon_months,
            memo: createForm.memo || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                createForm.name = '';
                createForm.memo = '';
            },
        },
    );
}

function calculate(simulation: MoneySimulationRow): void {
    router.post(`/yoyu/money/simulations/${simulation.id}/calculate`, {}, {
        preserveScroll: true,
    });
}

function discard(simulation: MoneySimulationRow): void {
    if (!confirm('このシミュレーションを破棄しますか？')) {
        return;
    }

    router.post(`/yoyu/money/simulations/${simulation.id}/discard`, {}, {
        preserveScroll: true,
    });
}

type MarginSlice = {
    label: string;
    projected_margin_minor?: string;
    safe_to_spend_minor?: string;
};

function asRecord(value: unknown): Record<string, unknown> | null {
    if (value !== null && typeof value === 'object' && !Array.isArray(value)) {
        return value as Record<string, unknown>;
    }

    return null;
}

function marginSlices(simulation: MoneySimulationRow): MarginSlice[] {
    const payload = asRecord(simulation.result_payload);
    const margins = asRecord(payload?.margins);

    if (!margins) {
        return [];
    }

    const labels: Array<{ key: string; label: string }> = [
        { key: 'this_month', label: '今月' },
        { key: 'next_month', label: '来月' },
        { key: 'three_months', label: '3か月後' },
        { key: 'month_0', label: '今月' },
        { key: 'month_1', label: '来月' },
        { key: 'month_2', label: '3か月目' },
    ];

    const seen = new Set<string>();
    const rows: MarginSlice[] = [];

    for (const item of labels) {
        const slice = asRecord(margins[item.key]);

        if (!slice || seen.has(item.label)) {
            continue;
        }

        seen.add(item.label);
        rows.push({
            label: item.label,
            projected_margin_minor:
                minorToDisplayString(
                    slice.projected_margin_minor as string | number | null | undefined,
                ) ?? undefined,
            safe_to_spend_minor:
                minorToDisplayString(
                    slice.safe_to_spend_minor as string | number | null | undefined,
                ) ?? undefined,
        });
    }

    // Fallback: iterate object keys if named slices missing.
    if (rows.length === 0) {
        for (const [key, value] of Object.entries(margins)) {
            const slice = asRecord(value);

            if (!slice) {
                continue;
            }

            rows.push({
                label: key,
                projected_margin_minor:
                    minorToDisplayString(
                        slice.projected_margin_minor as
                            | string
                            | number
                            | null
                            | undefined,
                    ) ?? undefined,
                safe_to_spend_minor:
                    minorToDisplayString(
                        slice.safe_to_spend_minor as
                            | string
                            | number
                            | null
                            | undefined,
                    ) ?? undefined,
            });
        }
    }

    return rows;
}

function paymentDelta(simulation: MoneySimulationRow): string | null {
    const payload = asRecord(simulation.result_payload);
    const change = asRecord(payload?.change_card_payment);

    if (!change) {
        return null;
    }

    const fee = minorToDisplayString(
        change.fee_estimate_minor as string | number | null | undefined,
    );
    const future = minorToDisplayString(
        change.future_monthly_minor as string | number | null | undefined,
    );
    const reduction = minorToDisplayString(
        change.this_month_reduction_minor as string | number | null | undefined,
    );

    if (!fee && !future && !reduction) {
        return null;
    }

    return [
        reduction ? `今月軽減 ${formatYen(reduction)}` : null,
        future ? `将来月額 ${formatYen(future)}` : null,
        fee ? `手数料見積 ${formatYen(fee)}` : null,
    ]
        .filter((part): part is string => part !== null)
        .join(' · ');
}

const hasAnyResult = computed(() =>
    props.simulations.some((item) => item.result_payload != null),
);

defineOptions({
    layout: {
        title: 'ヨユウ',
        subtitle: 'シミュレーター',
    },
});
</script>

<template>
    <div class="mx-auto max-w-[720px] space-y-4">
        <Head title="シミュレーター — お金の余裕" />

        <MoneySubnav active="simulations" />

        <section
            class="rounded-[18px] border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-2 text-sm font-bold text-os-ink">下書きを作成</h2>
            <p class="mb-3 text-[12px] text-os-sub">
                計算は実データを変更しません。適用するまで口座・予定は変わりません。
            </p>
            <form
                class="flex flex-wrap items-end gap-3"
                @submit.prevent="submitCreate"
            >
                <label class="text-[12px] text-os-sub">
                    名前
                    <input
                        v-model="createForm.name"
                        type="text"
                        class="mt-1 block w-40 rounded-lg border border-os-line px-2 py-1.5 text-[13px] text-os-ink"
                    />
                </label>
                <label class="text-[12px] text-os-sub">
                    期間（月）
                    <input
                        v-model.number="createForm.horizon_months"
                        type="number"
                        min="1"
                        max="24"
                        class="mt-1 block w-20 rounded-lg border border-os-line px-2 py-1.5 text-[13px] text-os-ink"
                    />
                </label>
                <Button type="submit" size="sm" class="rounded-full">
                    作成
                </Button>
            </form>
        </section>

        <section
            class="rounded-[18px] border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-3 text-sm font-bold text-os-ink">シミュレーション</h2>
            <p
                v-if="simulations.length === 0"
                class="text-[13px] text-os-sub"
            >
                まだありません。
            </p>
            <ul v-else class="space-y-4">
                <li
                    v-for="simulation in simulations"
                    :key="simulation.id"
                    class="rounded-xl border border-os-line px-4 py-3"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-2"
                    >
                        <div>
                            <p class="font-bold text-os-ink">
                                {{ simulation.name || '(無題)' }}
                            </p>
                            <p class="text-[12px] text-os-sub">
                                {{ simulation.status }} · 基準
                                {{ simulation.base_date }} ·
                                {{ simulation.horizon_months }}か月
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="calculate(simulation)"
                            >
                                計算
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                class="text-[#C05A48]"
                                @click="discard(simulation)"
                            >
                                破棄
                            </Button>
                        </div>
                    </div>

                    <div
                        v-if="marginSlices(simulation).length > 0"
                        class="mt-3 space-y-2"
                    >
                        <p class="text-[12px] font-semibold text-os-sub">
                            余裕の比較
                        </p>
                        <ul class="grid gap-2 sm:grid-cols-3">
                            <li
                                v-for="slice in marginSlices(simulation)"
                                :key="`${simulation.id}-${slice.label}`"
                                class="rounded-lg bg-os-yoyu-soft/50 px-3 py-2"
                            >
                                <p class="text-[11px] text-os-sub">
                                    {{ slice.label }}
                                </p>
                                <p class="text-[13px] font-bold text-os-ink">
                                    {{
                                        formatSignedYen(
                                            slice.projected_margin_minor ?? '0',
                                        )
                                    }}
                                </p>
                                <p
                                    v-if="slice.safe_to_spend_minor"
                                    class="text-[11px] text-os-sub"
                                >
                                    安全額
                                    {{ formatYen(slice.safe_to_spend_minor) }}
                                </p>
                            </li>
                        </ul>
                        <p
                            v-if="paymentDelta(simulation)"
                            class="text-[12px] text-os-sub"
                        >
                            支払差分: {{ paymentDelta(simulation) }}
                        </p>
                    </div>
                </li>
            </ul>
            <p
                v-if="!hasAnyResult && simulations.length > 0"
                class="mt-3 text-[12px] text-os-sub"
            >
                「計算」を実行すると比較結果が表示されます。助言やおすすめは出しません。
            </p>
        </section>
    </div>
</template>
