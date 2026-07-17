<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import { Button } from '@/components/ui/button';
import MoneySubnav from '@/components/yoyu-money/MoneySubnav.vue';
import { formatYen } from '@/lib/yoyuMoney/format';
import type { MoneyCashflowRow } from '@/lib/yoyuMoney/types';

interface Props {
    cashflows: MoneyCashflowRow[];
}

defineProps<Props>();

const createForm = reactive({
    name: '',
    direction: 'outflow',
    kind: 'expense',
    amount_minor: '',
    due_on: '',
    certainty: 'confirmed',
});

const settleTarget = ref<MoneyCashflowRow | null>(null);
const settleOccurredOn = ref('');
const settleDialog = ref<HTMLDialogElement | null>(null);

function submitCreate(): void {
    router.post(
        '/yoyu/money/cashflows',
        {
            name: createForm.name,
            direction: createForm.direction,
            kind: createForm.kind,
            amount_minor: createForm.amount_minor,
            due_on: createForm.due_on,
            certainty: createForm.certainty,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                createForm.name = '';
                createForm.amount_minor = '';
                createForm.due_on = '';
            },
        },
    );
}

function openSettle(cashflow: MoneyCashflowRow): void {
    settleTarget.value = cashflow;
    settleOccurredOn.value = cashflow.due_on;
    settleDialog.value?.showModal();
}

function confirmSettle(): void {
    const target = settleTarget.value;

    if (!target || settleOccurredOn.value === '') {
        return;
    }

    router.post(
        `/yoyu/money/cashflows/${target.id}/settle`,
        {
            amount_minor: target.amount.amountMinor,
            occurred_on: settleOccurredOn.value,
            create_transaction: true,
            update_balance: true,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                settleDialog.value?.close();
                settleTarget.value = null;
            },
        },
    );
}

function cancelCashflow(cashflow: MoneyCashflowRow): void {
    if (!confirm(`「${cashflow.name}」を取り消しますか？`)) {
        return;
    }

    router.delete(`/yoyu/money/cashflows/${cashflow.id}`, {
        data: { lock_version: cashflow.lock_version },
        preserveScroll: true,
    });
}

defineOptions({
    layout: {
        title: 'ヨユウ',
        subtitle: '入出金予定',
    },
});
</script>

<template>
    <div class="mx-auto max-w-[720px] space-y-4">
        <Head title="入出金 — お金の余裕" />

        <MoneySubnav active="cashflows" />

        <section
            class="rounded-[18px] border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-3 text-sm font-bold text-os-ink">予定を追加</h2>
            <form
                class="flex flex-wrap items-end gap-3"
                @submit.prevent="submitCreate"
            >
                <label class="text-[12px] text-os-sub">
                    名前
                    <input
                        v-model="createForm.name"
                        type="text"
                        required
                        class="mt-1 block w-40 rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                    />
                </label>
                <label class="text-[12px] text-os-sub">
                    方向
                    <select
                        v-model="createForm.direction"
                        class="mt-1 block w-28 rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                        @change="
                            createForm.kind =
                                createForm.direction === 'inflow'
                                    ? 'income'
                                    : 'expense'
                        "
                    >
                        <option value="inflow">収入</option>
                        <option value="outflow">支出</option>
                    </select>
                </label>
                <label class="text-[12px] text-os-sub">
                    種別
                    <select
                        v-model="createForm.kind"
                        class="mt-1 block w-36 rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                    >
                        <option value="income">収入</option>
                        <option value="expense">支出</option>
                        <option value="card_statement">カード請求</option>
                        <option value="loan_payment">ローン返済</option>
                        <option value="transfer">振替</option>
                    </select>
                </label>
                <label class="text-[12px] text-os-sub">
                    金額（円）
                    <input
                        v-model="createForm.amount_minor"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        required
                        class="mt-1 block w-28 rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                    />
                </label>
                <label class="text-[12px] text-os-sub">
                    期日
                    <input
                        v-model="createForm.due_on"
                        type="date"
                        required
                        class="mt-1 block rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                    />
                </label>
                <label class="text-[12px] text-os-sub">
                    確度
                    <select
                        v-model="createForm.certainty"
                        class="mt-1 block w-28 rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                    >
                        <option value="confirmed">確定</option>
                        <option value="expected">見込み</option>
                    </select>
                </label>
                <Button type="submit" size="sm" class="rounded-full">
                    追加
                </Button>
            </form>
        </section>

        <section
            class="rounded-[18px] border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-3 text-sm font-bold text-os-ink">予定一覧</h2>
            <p v-if="cashflows.length === 0" class="text-[13px] text-os-sub">
                予定はまだありません。
            </p>
            <ul v-else class="divide-y divide-os-line">
                <li
                    v-for="cashflow in cashflows"
                    :key="cashflow.id"
                    class="flex flex-wrap items-center justify-between gap-3 py-3"
                >
                    <div>
                        <p class="text-[14px] font-semibold text-os-ink">
                            {{ cashflow.name }}
                        </p>
                        <p class="text-[12px] text-os-sub">
                            {{ cashflow.due_on }} · {{ cashflow.direction }} ·
                            {{ cashflow.kind }} · {{ cashflow.status }} ·
                            {{ cashflow.certainty }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-bold text-os-ink">
                            {{ formatYen(cashflow.amount.amountMinor) }}
                        </span>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="openSettle(cashflow)"
                        >
                            消し込み
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            class="text-[#C05A48]"
                            @click="cancelCashflow(cashflow)"
                        >
                            取消
                        </Button>
                    </div>
                </li>
            </ul>
        </section>

        <dialog
            ref="settleDialog"
            class="w-[min(100%,420px)] rounded-[18px] border border-os-line bg-white p-5 text-os-ink shadow-lg backdrop:bg-black/30"
        >
            <h3 class="text-sm font-bold">消し込み確認</h3>
            <p v-if="settleTarget" class="mt-2 text-[13px] text-os-sub">
                「{{ settleTarget.name }}」（{{
                    formatYen(settleTarget.amount.amountMinor)
                }}）を消し込みます。発生日を入力してください。
            </p>
            <label class="mt-3 block text-[12px] text-os-sub">
                発生日
                <input
                    v-model="settleOccurredOn"
                    type="date"
                    required
                    class="mt-1 block w-full rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                />
            </label>
            <div class="mt-4 flex justify-end gap-2">
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    @click="settleDialog?.close()"
                >
                    キャンセル
                </Button>
                <Button type="button" size="sm" @click="confirmSettle">
                    消し込む
                </Button>
            </div>
        </dialog>
    </div>
</template>
