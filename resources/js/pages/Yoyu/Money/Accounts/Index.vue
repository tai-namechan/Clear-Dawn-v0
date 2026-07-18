<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Button } from '@/components/ui/button';
import MoneySubnav from '@/components/yoyu-money/MoneySubnav.vue';
import { formatYen } from '@/lib/yoyuMoney/format';
import type { MoneyAccountRow } from '@/lib/yoyuMoney/types';

interface Props {
    accounts: MoneyAccountRow[];
}

defineProps<Props>();

const createForm = reactive({
    name: '',
    type: 'bank',
    current_balance_minor: '0',
});

const balanceDrafts = reactive<Record<string, string>>({});

function draftFor(account: MoneyAccountRow): string {
    if (!(account.id in balanceDrafts)) {
        balanceDrafts[account.id] = account.current_balance.amountMinor;
    }

    return balanceDrafts[account.id];
}

function submitCreate(): void {
    router.post(
        '/yoyu/money/accounts',
        {
            name: createForm.name,
            type: createForm.type,
            current_balance_minor: createForm.current_balance_minor,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                createForm.name = '';
                createForm.type = 'bank';
                createForm.current_balance_minor = '0';
            },
        },
    );
}

function submitBalance(account: MoneyAccountRow): void {
    router.patch(
        `/yoyu/money/accounts/${account.id}/balance`,
        {
            current_balance_minor: draftFor(account),
            lock_version: account.lock_version,
        },
        { preserveScroll: true },
    );
}

function toggleActive(account: MoneyAccountRow): void {
    router.patch(
        `/yoyu/money/accounts/${account.id}/toggle`,
        { is_active: !account.is_active },
        { preserveScroll: true },
    );
}

const typeLabels: Record<string, string> = {
    bank: '銀行',
    cash: '現金',
    electronic_money: '電子マネー',
    other: 'その他',
};

defineOptions({
    layout: {
        title: 'ヨユウ',
        subtitle: '口座',
    },
});
</script>

<template>
    <div class="mx-auto max-w-[720px] space-y-4">
        <Head title="口座 — お金の余裕" />

        <MoneySubnav active="accounts" />

        <section
            class="rounded-[18px] border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-3 text-sm font-bold text-os-ink">口座を追加</h2>
            <form class="flex flex-wrap items-end gap-3" @submit.prevent="submitCreate">
                <label class="text-[12px] text-os-sub">
                    名前
                    <input
                        v-model="createForm.name"
                        type="text"
                        required
                        class="mt-1 block w-44 rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                    />
                </label>
                <label class="text-[12px] text-os-sub">
                    種別
                    <select
                        v-model="createForm.type"
                        class="mt-1 block w-36 rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                    >
                        <option value="bank">銀行</option>
                        <option value="cash">現金</option>
                        <option value="electronic_money">電子マネー</option>
                        <option value="other">その他</option>
                    </select>
                </label>
                <label class="text-[12px] text-os-sub">
                    残高（円・整数）
                    <input
                        v-model="createForm.current_balance_minor"
                        type="text"
                        inputmode="numeric"
                        pattern="-?[0-9]*"
                        required
                        class="mt-1 block w-32 rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                    />
                </label>
                <Button type="submit" size="sm" class="rounded-full">
                    追加
                </Button>
            </form>
        </section>

        <section
            class="rounded-[18px] border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-3 text-sm font-bold text-os-ink">口座一覧</h2>
            <p v-if="accounts.length === 0" class="text-[13px] text-os-sub">
                口座がまだありません。
            </p>
            <ul v-else class="space-y-4">
                <li
                    v-for="account in accounts"
                    :key="account.id"
                    class="rounded-xl border border-os-line px-4 py-3"
                >
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="font-bold text-os-ink">
                                {{ account.name }}
                                <span
                                    v-if="!account.is_active"
                                    class="ml-1 text-[11px] font-semibold text-os-sub"
                                >
                                    （停止中）
                                </span>
                            </p>
                            <p class="text-[12px] text-os-sub">
                                {{ typeLabels[account.type] ?? account.type }} ·
                                {{ account.currency_code }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold text-os-ink">
                                {{ formatYen(account.current_balance.amountMinor) }}
                            </p>
                            <p
                                v-if="account.available_balance"
                                class="text-[12px] text-os-sub"
                            >
                                利用可能
                                {{
                                    formatYen(
                                        account.available_balance.amountMinor,
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                    <form
                        class="mt-3 flex flex-wrap items-end gap-2"
                        @submit.prevent="submitBalance(account)"
                    >
                        <label class="text-[12px] text-os-sub">
                            残高を更新
                            <input
                                :value="draftFor(account)"
                                type="text"
                                inputmode="numeric"
                                pattern="-?[0-9]*"
                                required
                                class="mt-1 block w-32 rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                                @input="
                                    balanceDrafts[account.id] = (
                                        $event.target as HTMLInputElement
                                    ).value
                                "
                            />
                        </label>
                        <Button type="submit" size="sm" variant="outline">
                            更新
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="toggleActive(account)"
                        >
                            {{ account.is_active ? '停止' : '有効化' }}
                        </Button>
                    </form>
                </li>
            </ul>
        </section>
    </div>
</template>
