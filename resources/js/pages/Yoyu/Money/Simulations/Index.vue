<script setup lang="ts">
import { usePage, router } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import MoneyEmptyState from '@/components/yoyu-money/MoneyEmptyState.vue';
import MoneyPageShell from '@/components/yoyu-money/MoneyPageShell.vue';
import { simulationStatusLabel } from '@/lib/yoyuMoney/labels';
import { formatSignedYen, formatYen, minorToDisplayString } from '@/lib/yoyuMoney/format';
import { moneyPlanTabs } from '@/lib/yoyuMoney/navigation';
import type { MoneySimulationRow } from '@/lib/yoyuMoney/types';

interface Props {
    simulations: MoneySimulationRow[];
}

const props = defineProps<Props>();

const page = usePage();
const drawerOpen = ref(false);

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
                drawerOpen.value = false;
            },
        },
    );
}

function calculate(simulation: MoneySimulationRow): void {
    router.post(
        `/yoyu/money/simulations/${simulation.id}/calculate`,
        {},
        { preserveScroll: true },
    );
}

function discard(simulation: MoneySimulationRow): void {
    if (!confirm('このシミュレーションを破棄しますか？')) {
        return;
    }

    router.post(
        `/yoyu/money/simulations/${simulation.id}/discard`,
        {},
        { preserveScroll: true },
    );
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
                        slice.projected_margin_minor as string | number | null | undefined,
                    ) ?? undefined,
                safe_to_spend_minor:
                    minorToDisplayString(
                        slice.safe_to_spend_minor as string | number | null | undefined,
                    ) ?? undefined,
            });
        }
    }

    return rows;
}

type PaymentDeltaInfo = {
    thisMonthReduction: string | null;
    futureMonthly: string | null;
    feeEstimate: string | null;
};

function paymentDeltaInfo(simulation: MoneySimulationRow): PaymentDeltaInfo | null {
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

    return {
        thisMonthReduction: reduction,
        futureMonthly: future,
        feeEstimate: fee,
    };
}

const hasAnyResult = computed(() =>
    props.simulations.some((item) => item.result_payload != null),
);

onMounted(() => {
    if (page.url.includes('compose=1')) {
        drawerOpen.value = true;
    }
});

defineOptions({
    layout: {
        title: 'ヨユウ',
        subtitle: 'シミュレーター',
    },
});
</script>

<template>
    <MoneyPageShell
        title="シミュレーター"
        :section-tabs="moneyPlanTabs"
        section-active="simulations"
        section-label="計画"
        primary-active="plan"
        :show-record-menu="false"
    >
        <template #actions>
            <Button type="button" class="rounded-lg" @click="drawerOpen = true">
                ＋シナリオを作成
            </Button>
        </template>

        <div
            class="rounded-xl bg-os-yoyu-soft/60 px-3 py-2 text-[12.5px] text-os-ink"
        >
            計算結果は参考値です。「適用」ボタンを押すまで実データは変わりません。
        </div>

        <MoneyEmptyState
            v-if="simulations.length === 0 && !hasAnyResult"
            title="シミュレーションがまだありません"
            description="支払い計画の変更が余裕にどう影響するか、シナリオを作って比較できます。"
            action-label="シナリオを作成"
            action-href="/yoyu/money/simulations?compose=1"
        />

        <ul v-else class="space-y-4">
            <li
                v-for="simulation in simulations"
                :key="simulation.id"
                class="rounded-2xl border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="font-bold text-os-ink">
                            {{ simulation.name || '(無題)' }}
                        </p>
                        <p class="text-[12px] text-os-sub">
                            {{ simulationStatusLabel(simulation.status) }} ·
                            基準 {{ simulation.base_date }} ·
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
                            class="text-[#8A5A3B]"
                            @click="discard(simulation)"
                        >
                            破棄
                        </Button>
                    </div>
                </div>

                <!-- Margin comparison -->
                <div
                    v-if="marginSlices(simulation).length > 0"
                    class="mt-4 space-y-2"
                >
                    <p class="text-[12px] font-semibold text-os-sub">余裕の比較（試算）</p>
                    <ul class="grid gap-2 sm:grid-cols-3">
                        <li
                            v-for="slice in marginSlices(simulation)"
                            :key="`${simulation.id}-${slice.label}`"
                            class="rounded-xl bg-os-yoyu-soft/50 px-3 py-2"
                        >
                            <p class="text-[11px] text-os-sub">{{ slice.label }}</p>
                            <p class="text-[14px] font-bold text-os-ink">
                                {{
                                    formatSignedYen(
                                        slice.projected_margin_minor ?? '0',
                                    )
                                }}
                            </p>
                            <p v-if="slice.safe_to_spend_minor" class="text-[11px] text-os-faint">
                                安全額 {{ formatYen(slice.safe_to_spend_minor) }}
                            </p>
                        </li>
                    </ul>
                </div>

                <!-- Payment delta info -->
                <div
                    v-if="paymentDeltaInfo(simulation)"
                    class="mt-3 rounded-xl border border-os-line px-3 py-2 text-[12px]"
                >
                    <p class="font-semibold text-os-sub">支払差分の詳細</p>
                    <dl class="mt-1 space-y-0.5 text-os-ink">
                        <div
                            v-if="paymentDeltaInfo(simulation)?.thisMonthReduction"
                            class="flex justify-between gap-4"
                        >
                            <dt class="text-os-sub">今月の軽減額</dt>
                            <dd class="font-semibold">
                                {{
                                    formatYen(
                                        paymentDeltaInfo(simulation)?.thisMonthReduction ?? '0',
                                    )
                                }}
                            </dd>
                        </div>
                        <div
                            v-if="paymentDeltaInfo(simulation)?.futureMonthly"
                            class="flex justify-between gap-4"
                        >
                            <dt class="text-os-sub">将来の月額負担</dt>
                            <dd class="font-semibold">
                                {{
                                    formatYen(
                                        paymentDeltaInfo(simulation)?.futureMonthly ?? '0',
                                    )
                                }}
                            </dd>
                        </div>
                        <div
                            v-if="paymentDeltaInfo(simulation)?.feeEstimate"
                            class="flex justify-between gap-4"
                        >
                            <dt class="text-os-sub">手数料見積</dt>
                            <dd class="font-semibold">
                                {{
                                    formatYen(
                                        paymentDeltaInfo(simulation)?.feeEstimate ?? '0',
                                    )
                                }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <p
                    v-if="simulation.result_payload == null"
                    class="mt-3 text-[12px] text-os-faint"
                >
                    「計算」を押すと試算結果が表示されます。
                </p>
            </li>
        </ul>

        <Sheet :open="drawerOpen" @update:open="drawerOpen = $event">
            <SheetContent side="right" class="w-full border-os-line bg-white sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>シナリオを作成</SheetTitle>
                    <SheetDescription>
                        計算は実データを変更しません。「適用」するまで口座・予定は変わりません。
                    </SheetDescription>
                </SheetHeader>
                <form class="mt-4 space-y-3 px-1" @submit.prevent="submitCreate">
                    <label class="block text-[12px] text-os-sub">
                        名前（任意）
                        <input
                            v-model="createForm.name"
                            type="text"
                            class="mt-1 block w-full rounded-lg border border-os-line px-3 py-2 text-[13px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/40"
                            placeholder="例: 分割払いに変更した場合"
                        />
                    </label>
                    <label class="block text-[12px] text-os-sub">
                        投影期間（月）
                        <input
                            v-model.number="createForm.horizon_months"
                            type="number"
                            min="1"
                            max="24"
                            class="mt-1 block w-full rounded-lg border border-os-line px-3 py-2 text-[13px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/40"
                        />
                    </label>
                    <div class="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" @click="drawerOpen = false">
                            キャンセル
                        </Button>
                        <Button type="submit">作成する</Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    </MoneyPageShell>
</template>
