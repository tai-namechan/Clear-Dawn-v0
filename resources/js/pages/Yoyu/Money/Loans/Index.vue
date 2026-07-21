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
import MoneyEmptyState from '@/components/yoyu-money/MoneyEmptyState.vue';
import MoneyPageShell from '@/components/yoyu-money/MoneyPageShell.vue';
import { loanStatusLabel } from '@/lib/yoyuMoney/labels';
import { formatYen } from '@/lib/yoyuMoney/format';
import { moneyAssetsTabs } from '@/lib/yoyuMoney/navigation';
import type { MoneyLoanRow } from '@/lib/yoyuMoney/types';

interface Props {
    loans: MoneyLoanRow[];
}

defineProps<Props>();

const page = usePage();
const drawerOpen = ref(false);

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
                drawerOpen.value = false;
            },
        },
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
        subtitle: 'ローン',
    },
});
</script>

<template>
    <MoneyPageShell
        title="ローン"
        :section-tabs="moneyAssetsTabs"
        section-active="loans"
        section-label="資産・返済"
        primary-active="assets"
    >
        <template #actions>
            <Button type="button" class="rounded-lg" @click="drawerOpen = true">
                ＋ローンを追加
            </Button>
        </template>

        <MoneyEmptyState
            v-if="loans.length === 0"
            title="ローンがまだありません"
            description="ローンや分割払いを登録すると、月々の返済負担を余裕計算に反映できます。"
            action-label="ローンを追加"
            action-href="/yoyu/money/loans?compose=1"
        />

        <section
            v-else
            class="overflow-hidden rounded-2xl border border-os-line bg-white shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <!-- Desktop table -->
            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full text-left text-[13px]">
                    <thead class="border-b border-os-line bg-os-yoyu-bg/80 text-os-sub">
                        <tr>
                            <th class="px-4 py-2.5 font-semibold">ローン名</th>
                            <th class="px-4 py-2.5 font-semibold">種別</th>
                            <th class="px-4 py-2.5 text-right font-semibold">残高</th>
                            <th class="px-4 py-2.5 text-right font-semibold">月々返済</th>
                            <th class="px-4 py-2.5 font-semibold">次回返済</th>
                            <th class="px-4 py-2.5 font-semibold">状態</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="loan in loans"
                            :key="loan.id"
                            class="border-b border-os-line/80"
                        >
                            <td class="px-4 py-3 font-semibold text-os-ink">{{ loan.name }}</td>
                            <td class="px-4 py-3 text-os-sub">
                                {{ typeLabels[loan.type] ?? loan.type }}
                            </td>
                            <td class="px-4 py-3 text-right font-bold text-os-ink">
                                {{ formatYen(loan.outstanding_principal.amountMinor) }}
                            </td>
                            <td class="px-4 py-3 text-right text-os-sub">
                                {{ formatYen(loan.monthly_payment.amountMinor) }}
                            </td>
                            <td class="px-4 py-3 tabular-nums text-os-sub">
                                {{ loan.next_payment_on }}
                            </td>
                            <td class="px-4 py-3 text-os-faint text-[12px]">
                                {{ loanStatusLabel(loan.status) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards -->
            <ul class="divide-y divide-os-line md:hidden">
                <li
                    v-for="loan in loans"
                    :key="`m-${loan.id}`"
                    class="flex items-start justify-between gap-3 p-4"
                >
                    <div class="min-w-0">
                        <p class="font-semibold text-os-ink">{{ loan.name }}</p>
                        <p class="text-[12px] text-os-sub">
                            {{ typeLabels[loan.type] ?? loan.type }} ·
                            {{ loanStatusLabel(loan.status) }} · 次回 {{ loan.next_payment_on }}
                        </p>
                    </div>
                    <div class="shrink-0 text-right text-[13px]">
                        <p class="font-bold text-os-ink">
                            {{ formatYen(loan.outstanding_principal.amountMinor) }}
                        </p>
                        <p class="text-os-sub">
                            月々 {{ formatYen(loan.monthly_payment.amountMinor) }}
                        </p>
                    </div>
                </li>
            </ul>
        </section>

        <Sheet :open="drawerOpen" @update:open="drawerOpen = $event">
            <SheetContent side="right" class="w-full border-os-line bg-white sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>ローンを追加</SheetTitle>
                    <SheetDescription>
                        月々の返済額が余裕計算の支払い予定に自動反映されます。
                    </SheetDescription>
                </SheetHeader>
                <form class="mt-4 space-y-3 px-1" @submit.prevent="submitCreate">
                    <label class="block text-[12px] text-os-sub">
                        名前
                        <input
                            v-model="createForm.name"
                            type="text"
                            required
                            class="mt-1 block w-full rounded-lg border border-os-line px-3 py-2 text-[13px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/40"
                        />
                    </label>
                    <label class="block text-[12px] text-os-sub">
                        種別
                        <select
                            v-model="createForm.type"
                            class="mt-1 block w-full rounded-lg border border-os-line bg-white px-3 py-2 text-[13px]"
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
                    <label class="block text-[12px] text-os-sub">
                        残高（円）
                        <input
                            v-model="createForm.outstanding_principal_minor"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            required
                            class="mt-1 block w-full rounded-lg border border-os-line px-3 py-2 text-[13px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/40"
                        />
                    </label>
                    <label class="block text-[12px] text-os-sub">
                        月々の返済額（円）
                        <input
                            v-model="createForm.monthly_payment_minor"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            required
                            class="mt-1 block w-full rounded-lg border border-os-line px-3 py-2 text-[13px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/40"
                        />
                    </label>
                    <label class="block text-[12px] text-os-sub">
                        次回返済日
                        <input
                            v-model="createForm.next_payment_on"
                            type="date"
                            required
                            class="mt-1 block w-full rounded-lg border border-os-line px-3 py-2 text-[13px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/40"
                        />
                    </label>
                    <div class="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" @click="drawerOpen = false">
                            キャンセル
                        </Button>
                        <Button type="submit">追加する</Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    </MoneyPageShell>
</template>
