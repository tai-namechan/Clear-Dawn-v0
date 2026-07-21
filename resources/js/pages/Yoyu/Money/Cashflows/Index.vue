<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref, watch } from 'vue';
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
import MoneyStatusBadge from '@/components/yoyu-money/MoneyStatusBadge.vue';
import {
    cashflowStatusLabel,
    certaintyLabel,
    directionLabel,
    flexibilityLabel,
} from '@/lib/yoyuMoney/labels';
import type { MoneyAmountDto } from '@/lib/yoyuMoney/types';

type CashflowRow = {
    id: string;
    name: string;
    direction: string;
    kind: string;
    status: string;
    certainty: string;
    flexibility: string;
    due_on: string;
    amount: MoneyAmountDto;
    remaining_minor: string;
    balance_after_minor: string | null;
    category_name: string | null;
    counterparty_name: string | null;
    payment_method: string | null;
    is_essential: boolean;
    lock_version: number;
};

type Filters = {
    direction: string;
    status: string;
    certainty: string;
    q: string;
    sort: string;
};

interface Props {
    cashflows: CashflowRow[];
    month: string;
    as_of: string;
    funds_minor: string;
    compose?: string | null;
    highlight?: string | null;
    filters: Filters;
}

const props = defineProps<Props>();

const drawerOpen = ref(false);
const composeMode = ref<'income' | 'expense'>('expense');
const selected = ref<Record<string, boolean>>({});
const settleTarget = ref<CashflowRow | null>(null);
const settleOccurredOn = ref('');
const settleOpen = ref(false);

const localFilters = reactive({ ...props.filters });

const createForm = reactive({
    name: '',
    amount_minor: '',
    due_on: '',
    certainty: 'confirmed',
    flexibility: 'required',
});

const filtered = computed(() => {
    let rows = [...props.cashflows];

    if (
        localFilters.direction === 'inflow' ||
        localFilters.direction === 'outflow'
    ) {
        rows = rows.filter((row) => row.direction === localFilters.direction);
    }

    if (localFilters.status === 'open') {
        rows = rows.filter(
            (row) => row.status === 'planned' || row.status === 'partial',
        );
    } else if (localFilters.status === 'settled') {
        rows = rows.filter((row) => row.status === 'settled');
    }

    if (
        localFilters.certainty === 'confirmed' ||
        localFilters.certainty === 'expected'
    ) {
        rows = rows.filter((row) => row.certainty === localFilters.certainty);
    }

    const q = localFilters.q.trim().toLowerCase();

    if (q !== '') {
        rows = rows.filter(
            (row) =>
                row.name.toLowerCase().includes(q) ||
                (row.counterparty_name ?? '').toLowerCase().includes(q) ||
                (row.category_name ?? '').toLowerCase().includes(q),
        );
    }

    if (localFilters.sort === 'amount') {
        rows.sort((a, b) =>
            Number(BigInt(b.amount.amountMinor) - BigInt(a.amount.amountMinor)),
        );
    } else {
        rows.sort(
            (a, b) =>
                a.due_on.localeCompare(b.due_on) ||
                a.name.localeCompare(b.name),
        );
    }

    return rows;
});

function openCompose(mode: 'income' | 'expense'): void {
    composeMode.value = mode;
    createForm.name = '';
    createForm.amount_minor = '';
    createForm.due_on = '';
    createForm.certainty = 'confirmed';
    createForm.flexibility = mode === 'income' ? 'required' : 'required';
    drawerOpen.value = true;
}

function submitCreate(): void {
    const direction = composeMode.value === 'income' ? 'inflow' : 'outflow';
    const kind = composeMode.value === 'income' ? 'income' : 'expense';

    router.post(
        '/yoyu/money/cashflows',
        {
            name: createForm.name,
            direction,
            kind,
            amount_minor: createForm.amount_minor,
            due_on: createForm.due_on,
            certainty: createForm.certainty,
            flexibility: createForm.flexibility,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                drawerOpen.value = false;
            },
        },
    );
}

function openSettle(cashflow: CashflowRow): void {
    settleTarget.value = cashflow;
    settleOccurredOn.value = cashflow.due_on;
    settleOpen.value = true;
}

function confirmSettle(): void {
    const target = settleTarget.value;

    if (!target || settleOccurredOn.value === '') {
        return;
    }

    router.post(
        `/yoyu/money/cashflows/${target.id}/settle`,
        {
            amount_minor: target.remaining_minor,
            occurred_on: settleOccurredOn.value,
            create_transaction: true,
            update_balance: true,
            lock_version: target.lock_version,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                settleOpen.value = false;
                settleTarget.value = null;
            },
        },
    );
}

function cancelCashflow(cashflow: CashflowRow): void {
    if (!confirm(`「${cashflow.name}」を取り消しますか？`)) {
        return;
    }

    router.delete(`/yoyu/money/cashflows/${cashflow.id}`, {
        data: { lock_version: cashflow.lock_version },
        preserveScroll: true,
    });
}

function applyFilters(): void {
    router.get(
        '/yoyu/money/cashflows',
        {
            month: props.month,
            direction: localFilters.direction,
            status: localFilters.status,
            certainty: localFilters.certainty,
            q: localFilters.q || undefined,
            sort: localFilters.sort,
        },
        { preserveState: true, preserveScroll: true },
    );
}

function clearFilters(): void {
    localFilters.direction = 'all';
    localFilters.status = 'all';
    localFilters.certainty = 'all';
    localFilters.q = '';
    localFilters.sort = 'date';
    applyFilters();
}

function bulkSettle(): void {
    const targets = filtered.value.filter(
        (row) => selected.value[row.id] && row.status !== 'settled',
    );

    if (targets.length === 0) {
        return;
    }

    if (!confirm(`${targets.length}件を支払い済み／入金済みにしますか？`)) {
        return;
    }

    const run = (index: number): void => {
        const target = targets[index];

        if (!target) {
            return;
        }

        router.post(
            `/yoyu/money/cashflows/${target.id}/settle`,
            {
                amount_minor: target.remaining_minor,
                occurred_on: target.due_on,
                create_transaction: true,
                update_balance: true,
                lock_version: target.lock_version,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    if (index + 1 < targets.length) {
                        run(index + 1);
                    }
                },
            },
        );
    };

    run(0);
}

onMounted(() => {
    if (props.compose === 'income') {
        openCompose('income');
    } else if (props.compose === 'expense' || props.compose === '1') {
        openCompose('expense');
    }
});

watch(
    () => props.compose,
    (value) => {
        if (value === 'income') {
            openCompose('income');
        } else if (value === 'expense') {
            openCompose('expense');
        }
    },
);

defineOptions({
    layout: {
        title: 'ヨユウ',
        subtitle: '今月',
    },
});
</script>

<template>
    <MoneyPageShell
        title="今月"
        document-title="今月 — お金の余裕"
        :month="month"
        :as-of="as_of"
        show-month-switcher
        primary-active="month"
    >
        <template #actions>
            <Button
                type="button"
                class="rounded-lg"
                @click="openCompose('income')"
            >
                ＋収入予定を追加
            </Button>
            <Button
                type="button"
                class="rounded-lg"
                @click="openCompose('expense')"
            >
                ＋支払予定を追加
            </Button>
        </template>

        <section
            class="rounded-2xl border border-os-line bg-white p-4 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <div class="flex flex-wrap items-end gap-3">
                <label class="text-[12px] text-os-sub">
                    表示
                    <select
                        v-model="localFilters.direction"
                        class="mt-1 block rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px]"
                        @change="applyFilters"
                    >
                        <option value="all">すべて</option>
                        <option value="outflow">支払い</option>
                        <option value="inflow">収入</option>
                    </select>
                </label>
                <label class="text-[12px] text-os-sub">
                    状態
                    <select
                        v-model="localFilters.status"
                        class="mt-1 block rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px]"
                        @change="applyFilters"
                    >
                        <option value="all">すべて</option>
                        <option value="open">未処理のみ</option>
                        <option value="settled">処理済み</option>
                    </select>
                </label>
                <label class="text-[12px] text-os-sub">
                    確度
                    <select
                        v-model="localFilters.certainty"
                        class="mt-1 block rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px]"
                        @change="applyFilters"
                    >
                        <option value="all">すべて</option>
                        <option value="confirmed">確定</option>
                        <option value="expected">見込み</option>
                    </select>
                </label>
                <label class="text-[12px] text-os-sub">
                    並び
                    <select
                        v-model="localFilters.sort"
                        class="mt-1 block rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px]"
                        @change="applyFilters"
                    >
                        <option value="date">日付順</option>
                        <option value="amount">金額順</option>
                    </select>
                </label>
                <label class="min-w-[10rem] flex-1 text-[12px] text-os-sub">
                    検索
                    <input
                        v-model="localFilters.q"
                        type="search"
                        class="mt-1 block w-full rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px]"
                        @keydown.enter.prevent="applyFilters"
                    />
                </label>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    @click="clearFilters"
                >
                    フィルター解除
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    @click="bulkSettle"
                >
                    一括で処理済みにする
                </Button>
            </div>
        </section>

        <MoneyEmptyState
            v-if="filtered.length === 0"
            title="今月の予定はまだありません"
            description="収入予定や支払予定を追加すると、日付順の残高推移を確認できます。"
            action-label="支払予定を追加"
            action-href="/yoyu/money/cashflows?compose=expense"
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
                            <th class="px-3 py-2 font-semibold">選択</th>
                            <th class="px-3 py-2 font-semibold">日付</th>
                            <th class="px-3 py-2 font-semibold">区分</th>
                            <th class="px-3 py-2 font-semibold">名前</th>
                            <th class="px-3 py-2 font-semibold">カテゴリ</th>
                            <th class="px-3 py-2 text-right font-semibold">
                                金額
                            </th>
                            <th class="px-3 py-2 text-right font-semibold">
                                残高
                            </th>
                            <th class="px-3 py-2 font-semibold">状態</th>
                            <th class="px-3 py-2 font-semibold">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="cashflow in filtered"
                            :key="cashflow.id"
                            class="border-b border-os-line/80"
                            :class="
                                highlight === cashflow.id
                                    ? 'bg-os-yoyu-soft/50'
                                    : ''
                            "
                        >
                            <td class="px-3 py-2">
                                <input
                                    v-model="selected[cashflow.id]"
                                    type="checkbox"
                                    class="size-4"
                                    :aria-label="`${cashflow.name}を選択`"
                                />
                            </td>
                            <td class="px-3 py-2 tabular-nums">
                                {{ cashflow.due_on }}
                            </td>
                            <td class="px-3 py-2">
                                {{ directionLabel(cashflow.direction) }}
                            </td>
                            <td class="px-3 py-2">
                                <p
                                    class="max-w-[14rem] truncate font-semibold text-os-ink"
                                >
                                    {{ cashflow.name }}
                                </p>
                                <p
                                    v-if="cashflow.counterparty_name"
                                    class="truncate text-[11px] text-os-faint"
                                >
                                    {{ cashflow.counterparty_name }}
                                </p>
                            </td>
                            <td class="px-3 py-2 text-os-sub">
                                {{ cashflow.category_name ?? '—' }}
                            </td>
                            <td class="px-3 py-2 text-right font-semibold">
                                <MoneyAmount
                                    :amount-minor="
                                        cashflow.direction === 'inflow'
                                            ? cashflow.amount.amountMinor
                                            : `-${cashflow.amount.amountMinor}`
                                    "
                                    signed
                                />
                            </td>
                            <td class="px-3 py-2 text-right">
                                <MoneyAmount
                                    :amount-minor="cashflow.balance_after_minor"
                                />
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex flex-wrap gap-1">
                                    <MoneyStatusBadge
                                        :label="
                                            certaintyLabel(cashflow.certainty)
                                        "
                                        tone="info"
                                    />
                                    <MoneyStatusBadge
                                        :label="
                                            flexibilityLabel(
                                                cashflow.flexibility,
                                            )
                                        "
                                    />
                                    <MoneyStatusBadge
                                        :label="
                                            cashflowStatusLabel(cashflow.status)
                                        "
                                        :tone="
                                            cashflow.status === 'settled'
                                                ? 'positive'
                                                : 'neutral'
                                        "
                                    />
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex flex-wrap gap-1">
                                    <button
                                        v-if="cashflow.status !== 'settled'"
                                        type="button"
                                        class="rounded-md border border-os-line px-2 py-1 text-[11px] font-semibold hover:bg-os-yoyu-soft"
                                        @click="openSettle(cashflow)"
                                    >
                                        処理済み
                                    </button>
                                    <a
                                        v-if="
                                            cashflow.flexibility ===
                                                'adjustable' ||
                                            cashflow.flexibility === 'stoppable'
                                        "
                                        :href="`/yoyu/money/simulations?from_cashflow=${cashflow.id}`"
                                        class="rounded-md border border-os-line px-2 py-1 text-[11px] font-semibold text-os-yoyu hover:bg-os-yoyu-soft"
                                    >
                                        比較
                                    </a>
                                    <button
                                        type="button"
                                        class="rounded-md border border-os-line px-2 py-1 text-[11px] font-semibold text-[#8A5A3B] hover:bg-[#F3E8DF]"
                                        @click="cancelCashflow(cashflow)"
                                    >
                                        取消
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards -->
            <ul class="divide-y divide-os-line md:hidden">
                <li
                    v-for="cashflow in filtered"
                    :key="`m-${cashflow.id}`"
                    class="space-y-2 p-4"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold text-os-ink">
                                {{ cashflow.name }}
                            </p>
                            <p class="text-[12px] text-os-sub">
                                {{ cashflow.due_on }} ·
                                {{ directionLabel(cashflow.direction) }}
                            </p>
                        </div>
                        <MoneyAmount
                            :amount-minor="
                                cashflow.direction === 'inflow'
                                    ? cashflow.amount.amountMinor
                                    : `-${cashflow.amount.amountMinor}`
                            "
                            signed
                        />
                    </div>
                    <p class="text-[12px] text-os-faint">
                        イベント後残高
                        <MoneyAmount
                            :amount-minor="cashflow.balance_after_minor"
                        />
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-if="cashflow.status !== 'settled'"
                            type="button"
                            class="rounded-lg border border-os-line px-3 py-2 text-[12px] font-semibold"
                            @click="openSettle(cashflow)"
                        >
                            処理済みにする
                        </button>
                        <button
                            type="button"
                            class="rounded-lg border border-os-line px-3 py-2 text-[12px] font-semibold text-[#8A5A3B]"
                            @click="cancelCashflow(cashflow)"
                        >
                            取消
                        </button>
                    </div>
                </li>
            </ul>
        </section>

        <Sheet :open="drawerOpen" @update:open="drawerOpen = $event">
            <SheetContent
                side="right"
                class="w-full border-os-line bg-white sm:max-w-md"
            >
                <SheetHeader>
                    <SheetTitle>
                        {{
                            composeMode === 'income'
                                ? '収入予定を追加'
                                : '支払予定を追加'
                        }}
                    </SheetTitle>
                    <SheetDescription>
                        必要な項目だけ入力します。方向と種別は自動で設定されます。
                    </SheetDescription>
                </SheetHeader>
                <form
                    class="mt-4 space-y-3 px-1"
                    @submit.prevent="submitCreate"
                >
                    <label class="block text-[12px] text-os-sub">
                        名前
                        <input
                            v-model="createForm.name"
                            type="text"
                            required
                            class="mt-1 block w-full rounded-lg border border-os-line px-3 py-2 text-[13px] focus-visible:ring-2 focus-visible:ring-os-yoyu/40 focus-visible:outline-none"
                        />
                    </label>
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
                        日付
                        <input
                            v-model="createForm.due_on"
                            type="date"
                            required
                            class="mt-1 block w-full rounded-lg border border-os-line px-3 py-2 text-[13px] focus-visible:ring-2 focus-visible:ring-os-yoyu/40 focus-visible:outline-none"
                        />
                    </label>
                    <label class="block text-[12px] text-os-sub">
                        確定／見込み
                        <select
                            v-model="createForm.certainty"
                            class="mt-1 block w-full rounded-lg border border-os-line px-3 py-2 text-[13px]"
                        >
                            <option value="confirmed">確定</option>
                            <option value="expected">見込み</option>
                        </select>
                    </label>
                    <label
                        v-if="composeMode === 'expense'"
                        class="block text-[12px] text-os-sub"
                    >
                        柔軟性
                        <select
                            v-model="createForm.flexibility"
                            class="mt-1 block w-full rounded-lg border border-os-line px-3 py-2 text-[13px]"
                        >
                            <option value="required">必須</option>
                            <option value="adjustable">調整可能</option>
                            <option value="stoppable">停止可能</option>
                        </select>
                    </label>
                    <div class="flex justify-end gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="drawerOpen = false"
                        >
                            キャンセル
                        </Button>
                        <Button type="submit">追加する</Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>

        <Sheet :open="settleOpen" @update:open="settleOpen = $event">
            <SheetContent
                side="right"
                class="w-full border-os-line bg-white sm:max-w-md"
            >
                <SheetHeader>
                    <SheetTitle>処理済みにする</SheetTitle>
                    <SheetDescription v-if="settleTarget">
                        「{{ settleTarget.name }}」を処理済みにします。
                    </SheetDescription>
                </SheetHeader>
                <div class="mt-4 space-y-3 px-1">
                    <label class="block text-[12px] text-os-sub">
                        発生日
                        <input
                            v-model="settleOccurredOn"
                            type="date"
                            required
                            class="mt-1 block w-full rounded-lg border border-os-line px-3 py-2 text-[13px]"
                        />
                    </label>
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="settleOpen = false"
                        >
                            キャンセル
                        </Button>
                        <Button type="button" @click="confirmSettle"
                            >確定</Button
                        >
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    </MoneyPageShell>
</template>
