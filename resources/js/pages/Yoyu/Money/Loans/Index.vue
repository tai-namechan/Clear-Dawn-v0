<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Button } from '@/components/ui/button';
import MoneySubnav from '@/components/yoyu-money/MoneySubnav.vue';
import { formatYen } from '@/lib/yoyuMoney/format';
import type { MoneyLoanRow } from '@/lib/yoyuMoney/types';

interface Props {
    loans: MoneyLoanRow[];
}

defineProps<Props>();

const createForm = reactive({
    name: '',
    type: 'personal_loan',
    outstanding_principal_minor: '',
    monthly_payment_minor: '',
    next_payment_on: '',
});

function submitCreate(): void {
    router.post(
        '/yoyu/money/loans',
        {
            name: createForm.name,
            type: createForm.type,
            outstanding_principal_minor: createForm.outstanding_principal_minor,
            monthly_payment_minor: createForm.monthly_payment_minor,
            next_payment_on: createForm.next_payment_on,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                createForm.name = '';
                createForm.outstanding_principal_minor = '';
                createForm.monthly_payment_minor = '';
                createForm.next_payment_on = '';
            },
        },
    );
}

const typeLabels: Record<string, string> = {
    card_loan: 'カードローン',
    personal_loan: 'フリーローン',
    medical_loan: '医療ローン',
    shopping_loan: 'ショッピングローン',
    auto_loan: 'オートローン',
    student_loan: '奨学金',
    mortgage: '住宅ローン',
    pay_later: '後払い',
    other: 'その他',
};

defineOptions({
    layout: {
        title: 'ヨユウ',
        subtitle: 'ローン',
    },
});
</script>

<template>
    <div class="mx-auto max-w-[720px] space-y-4">
        <Head title="ローン — お金の余裕" />

        <MoneySubnav active="loans" />

        <section
            class="rounded-[18px] border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-3 text-sm font-bold text-os-ink">ローンを追加</h2>
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
                    種別
                    <select
                        v-model="createForm.type"
                        class="mt-1 block w-40 rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                    >
                        <option
                            v-for="(label, value) in typeLabels"
                            :key="value"
                            :value="value"
                        >
                            {{ label }}
                        </option>
                    </select>
                </label>
                <label class="text-[12px] text-os-sub">
                    残高（円）
                    <input
                        v-model="createForm.outstanding_principal_minor"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        required
                        class="mt-1 block w-28 rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                    />
                </label>
                <label class="text-[12px] text-os-sub">
                    月々返済（円）
                    <input
                        v-model="createForm.monthly_payment_minor"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        required
                        class="mt-1 block w-28 rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                    />
                </label>
                <label class="text-[12px] text-os-sub">
                    次回返済日
                    <input
                        v-model="createForm.next_payment_on"
                        type="date"
                        required
                        class="mt-1 block rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
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
            <h2 class="mb-3 text-sm font-bold text-os-ink">ローン一覧</h2>
            <p v-if="loans.length === 0" class="text-[13px] text-os-sub">
                ローンがまだありません。
            </p>
            <ul v-else class="divide-y divide-os-line">
                <li
                    v-for="loan in loans"
                    :key="loan.id"
                    class="flex flex-wrap items-center justify-between gap-3 py-3"
                >
                    <div>
                        <p class="font-bold text-os-ink">{{ loan.name }}</p>
                        <p class="text-[12px] text-os-sub">
                            {{ typeLabels[loan.type] ?? loan.type }} ·
                            {{ loan.status }} · 次回 {{ loan.next_payment_on }}
                        </p>
                    </div>
                    <div class="text-right text-[13px]">
                        <p class="font-bold text-os-ink">
                            {{
                                formatYen(
                                    loan.outstanding_principal.amountMinor,
                                )
                            }}
                        </p>
                        <p class="text-os-sub">
                            月々
                            {{ formatYen(loan.monthly_payment.amountMinor) }}
                        </p>
                    </div>
                </li>
            </ul>
        </section>
    </div>
</template>
