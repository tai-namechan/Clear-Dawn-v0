<script setup lang="ts">
import { usePage, router } from '@inertiajs/vue3';
import { onMounted, reactive, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import MoneyAmount from '@/components/yoyu-money/MoneyAmount.vue';
import MoneyEmptyState from '@/components/yoyu-money/MoneyEmptyState.vue';
import MoneyPageShell from '@/components/yoyu-money/MoneyPageShell.vue';
import {
    directionLabel,
    transactionKindLabel,
    transactionSpendHint,
} from '@/lib/yoyuMoney/labels';
import { moneyLedgerTabs } from '@/lib/yoyuMoney/navigation';
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

const page = usePage();
const drawerOpen = ref(false);

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
                drawerOpen.value = false;
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

function goPage(pageNum: number): void {
    if (pageNum < 1 || pageNum > props.pagination.last_page) {
        return;
    }

    router.get(
        '/yoyu/money/transactions',
        { page: pageNum },
        { preserveState: true, preserveScroll: true },
    );
}

onMounted(() => {
    if (page.url.includes('compose=1')) {
        drawerOpen.value = true;
    }
});

defineOptions({
    layout: {
        title: 'ヨユウ',
        subtitle: '明細',
    },
});
</script>

<template>
    <MoneyPageShell
        title="取引明細"
        :section-tabs="moneyLedgerTabs"
        section-active="transactions"
        section-label="明細"
        primary-active="ledger"
    >
        <template #actions>
            <Button
                type="button"
                class="rounded-lg"
                :disabled="accounts.length === 0"
                @click="drawerOpen = true"
            >
                ＋手入力で登録
            </Button>
        </template>

        <p
            v-if="accounts.length === 0"
            class="rounded-xl bg-os-yoyu-soft/60 px-3 py-2 text-[13px] text-os-sub"
        >
            先に口座を追加すると、取引を記録できます。
        </p>

        <MoneyEmptyState
            v-else-if="transactions.length === 0"
            title="明細がまだありません"
            description="手入力またはCSV取込で取引を登録できます。"
            action-label="取引を登録する"
            action-href="/yoyu/money/transactions?compose=1"
        />

        <section
            v-else
            class="overflow-hidden rounded-2xl border border-os-line bg-white shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <!-- Desktop table -->
            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full text-left text-[13px]">
                    <thead
                        class="border-b border-os-line bg-os-yoyu-bg/80 text-os-sub"
                    >
                        <tr>
                            <th class="px-4 py-2.5 font-semibold">日付</th>
                            <th class="px-4 py-2.5 font-semibold">摘要</th>
                            <th class="px-4 py-2.5 font-semibold">区分</th>
                            <th class="px-4 py-2.5 font-semibold">種別</th>
                            <th class="px-4 py-2.5 text-right font-semibold">
                                金額
                            </th>
                            <th class="px-4 py-2.5 font-semibold">参考</th>
                            <th class="px-4 py-2.5 font-semibold">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="tx in transactions"
                            :key="tx.id"
                            class="border-b border-os-line/80"
                            :class="tx.voided_at ? 'opacity-40' : ''"
                        >
                            <td class="px-4 py-3 text-os-sub tabular-nums">
                                {{ tx.occurred_on }}
                            </td>
                            <td class="px-4 py-3">
                                <p
                                    class="max-w-[14rem] truncate font-semibold text-os-ink"
                                >
                                    {{ tx.description || '(摘要なし)' }}
                                </p>
                                <p
                                    v-if="tx.voided_at"
                                    class="text-[11px] text-os-faint"
                                >
                                    取消済
                                </p>
                            </td>
                            <td class="px-4 py-3 text-os-sub">
                                {{ directionLabel(tx.direction) }}
                            </td>
                            <td class="px-4 py-3 text-os-sub">
                                {{ transactionKindLabel(tx.kind) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <MoneyAmount
                                    :amount-minor="
                                        tx.direction === 'inflow'
                                            ? tx.amount.amountMinor
                                            : `-${tx.amount.amountMinor}`
                                    "
                                    signed
                                />
                            </td>
                            <td class="px-4 py-3 text-[12px] text-os-faint">
                                {{ transactionSpendHint(tx.kind) }}
                            </td>
                            <td class="px-4 py-3">
                                <Button
                                    v-if="!tx.voided_at"
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    class="text-[#8A5A3B]"
                                    @click="voidTransaction(tx)"
                                >
                                    取消
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards -->
            <ul class="divide-y divide-os-line md:hidden">
                <li
                    v-for="tx in transactions"
                    :key="`m-${tx.id}`"
                    class="p-4"
                    :class="tx.voided_at ? 'opacity-40' : ''"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-os-ink">
                                {{ tx.description || '(摘要なし)' }}
                            </p>
                            <p class="text-[12px] text-os-sub">
                                {{ tx.occurred_on }} ·
                                {{ directionLabel(tx.direction) }} ·
                                {{ transactionKindLabel(tx.kind) }}
                            </p>
                            <p class="text-[11px] text-os-faint">
                                {{ transactionSpendHint(tx.kind) }}
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <MoneyAmount
                                :amount-minor="
                                    tx.direction === 'inflow'
                                        ? tx.amount.amountMinor
                                        : `-${tx.amount.amountMinor}`
                                "
                                signed
                            />
                        </div>
                    </div>
                    <div v-if="!tx.voided_at" class="mt-2">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            class="text-[#8A5A3B]"
                            @click="voidTransaction(tx)"
                        >
                            取消
                        </Button>
                    </div>
                </li>
            </ul>
        </section>

        <!-- Pagination -->
        <div
            v-if="pagination.last_page > 1"
            class="flex items-center justify-between text-[13px]"
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
                {{ pagination.current_page }} / {{ pagination.last_page }}（全
                {{ pagination.total }} 件）
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

        <Sheet :open="drawerOpen" @update:open="drawerOpen = $event">
            <SheetContent
                side="right"
                class="w-full border-os-line bg-white sm:max-w-md"
            >
                <SheetHeader>
                    <SheetTitle>取引を手入力</SheetTitle>
                    <SheetDescription>
                        口座残高に反映させたい場合は残高更新ページから操作してください。
                    </SheetDescription>
                </SheetHeader>
                <form
                    class="mt-4 space-y-3 px-1"
                    @submit.prevent="submitCreate"
                >
                    <label class="block text-[12px] text-os-sub">
                        口座
                        <select
                            v-model="createForm.account_id"
                            required
                            class="mt-1 block w-full rounded-lg border border-os-line bg-white px-3 py-2 text-[13px]"
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
                    <div class="grid grid-cols-2 gap-3">
                        <label class="text-[12px] text-os-sub">
                            方向
                            <select
                                v-model="createForm.direction"
                                class="mt-1 block w-full rounded-lg border border-os-line bg-white px-3 py-2 text-[13px]"
                            >
                                <option value="inflow">収入</option>
                                <option value="outflow">支出</option>
                            </select>
                        </label>
                        <label class="text-[12px] text-os-sub">
                            種別
                            <select
                                v-model="createForm.kind"
                                class="mt-1 block w-full rounded-lg border border-os-line bg-white px-3 py-2 text-[13px]"
                            >
                                <option value="purchase">利用</option>
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
                    </div>
                    <label class="block text-[12px] text-os-sub">
                        金額（円）
                        <input
                            v-model="createForm.amount_minor"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            required
                            class="mt-1 block w-full rounded-lg border border-os-line px-3 py-2 text-[13px] focus-visible:ring-2 focus-visible:ring-os-yoyu/40 focus-visible:outline-none"
                        />
                    </label>
                    <label class="block text-[12px] text-os-sub">
                        発生日
                        <input
                            v-model="createForm.occurred_on"
                            type="date"
                            required
                            class="mt-1 block w-full rounded-lg border border-os-line px-3 py-2 text-[13px] focus-visible:ring-2 focus-visible:ring-os-yoyu/40 focus-visible:outline-none"
                        />
                    </label>
                    <label class="block text-[12px] text-os-sub">
                        摘要（任意）
                        <input
                            v-model="createForm.description"
                            type="text"
                            class="mt-1 block w-full rounded-lg border border-os-line px-3 py-2 text-[13px] focus-visible:ring-2 focus-visible:ring-os-yoyu/40 focus-visible:outline-none"
                        />
                    </label>
                    <p class="text-[11px] text-os-faint">
                        {{ transactionSpendHint(createForm.kind) }}
                    </p>
                    <div class="flex justify-end gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="drawerOpen = false"
                        >
                            キャンセル
                        </Button>
                        <Button type="submit">登録する</Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    </MoneyPageShell>
</template>
