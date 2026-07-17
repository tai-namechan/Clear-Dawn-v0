<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Button } from '@/components/ui/button';
import MoneySubnav from '@/components/yoyu-money/MoneySubnav.vue';
import { formatYen } from '@/lib/yoyuMoney/format';
import type { MoneyTransactionRow } from '@/lib/yoyuMoney/types';

type Pagination = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type AccountOption = {
    id: string;
    name: string;
};

interface Props {
    transactions: MoneyTransactionRow[];
    accounts: AccountOption[];
    pagination: Pagination;
}

const props = defineProps<Props>();

const createForm = reactive({
    account_id: props.accounts[0]?.id ?? '',
    direction: 'outflow',
    kind: 'purchase',
    amount_minor: '',
    occurred_on: '',
    description: '',
});

function submitCreate(): void {
    router.post(
        '/yoyu/money/transactions',
        {
            account_id: createForm.account_id,
            direction: createForm.direction,
            kind: createForm.kind,
            amount_minor: createForm.amount_minor,
            occurred_on: createForm.occurred_on,
            description: createForm.description || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                createForm.amount_minor = '';
                createForm.description = '';
            },
        },
    );
}

function voidTransaction(transaction: MoneyTransactionRow): void {
    if (!confirm('この取引を取り消しますか？')) {
        return;
    }

    router.post(
        `/yoyu/money/transactions/${transaction.id}/void`,
        { reason: 'manual_void' },
        { preserveScroll: true },
    );
}

function goPage(page: number): void {
    if (page < 1 || page > props.pagination.last_page) {
        return;
    }

    router.get(
        '/yoyu/money/transactions',
        { page },
        { preserveState: true, preserveScroll: true },
    );
}

defineOptions({
    layout: {
        title: 'ヨユウ',
        subtitle: '明細',
    },
});
</script>

<template>
    <div class="mx-auto max-w-[720px] space-y-4">
        <Head title="明細 — お金の余裕" />

        <MoneySubnav active="transactions" />

        <section
            class="rounded-[18px] border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-3 text-sm font-bold text-os-ink">手入力で登録</h2>
            <form
                class="flex flex-wrap items-end gap-3"
                @submit.prevent="submitCreate"
            >
                <label class="text-[12px] text-os-sub">
                    口座
                    <select
                        v-model="createForm.account_id"
                        required
                        class="mt-1 block w-44 rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                    >
                        <option
                            v-for="account in accounts"
                            :key="account.id"
                            :value="account.id"
                        >
                            {{ account.name }}
                        </option>
                    </select>
                </label>
                <label class="text-[12px] text-os-sub">
                    方向
                    <select
                        v-model="createForm.direction"
                        class="mt-1 block w-28 rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
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
                        <option value="purchase">購入</option>
                        <option value="income">収入</option>
                        <option value="fee">手数料</option>
                        <option value="interest">利息</option>
                        <option value="refund">返金</option>
                        <option value="card_payment">カード支払</option>
                        <option value="loan_payment">ローン返済</option>
                        <option value="transfer">振替</option>
                        <option value="adjustment">調整</option>
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
                    発生日
                    <input
                        v-model="createForm.occurred_on"
                        type="date"
                        required
                        class="mt-1 block rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                    />
                </label>
                <label class="text-[12px] text-os-sub">
                    摘要
                    <input
                        v-model="createForm.description"
                        type="text"
                        class="mt-1 block w-44 rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                    />
                </label>
                <Button type="submit" size="sm" class="rounded-full">
                    登録
                </Button>
            </form>
            <p
                v-if="accounts.length === 0"
                class="mt-2 text-[12px] text-[#C05A48]"
            >
                先に口座を追加してください。
            </p>
        </section>

        <section
            class="rounded-[18px] border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-3 text-sm font-bold text-os-ink">明細</h2>
            <p
                v-if="transactions.length === 0"
                class="text-[13px] text-os-sub"
            >
                明細はまだありません。
            </p>
            <ul v-else class="divide-y divide-os-line">
                <li
                    v-for="tx in transactions"
                    :key="tx.id"
                    class="flex flex-wrap items-center justify-between gap-3 py-3"
                >
                    <div>
                        <p class="font-semibold text-os-ink">
                            {{ tx.description || '(摘要なし)' }}
                        </p>
                        <p class="text-[12px] text-os-sub">
                            {{ tx.occurred_on }} · {{ tx.direction }} ·
                            {{ tx.kind }} · {{ tx.status }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-os-ink">
                            {{ formatYen(tx.amount.amountMinor) }}
                        </span>
                        <Button
                            v-if="!tx.voided_at"
                            type="button"
                            size="sm"
                            variant="outline"
                            class="text-[#C05A48]"
                            @click="voidTransaction(tx)"
                        >
                            取消
                        </Button>
                    </div>
                </li>
            </ul>
            <div
                v-if="pagination.last_page > 1"
                class="mt-4 flex items-center justify-between text-[13px]"
            >
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    :disabled="pagination.current_page <= 1"
                    @click="goPage(pagination.current_page - 1)"
                >
                    前へ
                </Button>
                <span class="text-os-sub">
                    {{ pagination.current_page }} /
                    {{ pagination.last_page }}（全 {{ pagination.total }} 件）
                </span>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    :disabled="pagination.current_page >= pagination.last_page"
                    @click="goPage(pagination.current_page + 1)"
                >
                    次へ
                </Button>
            </div>
        </section>
    </div>
</template>
