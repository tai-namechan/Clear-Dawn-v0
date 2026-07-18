<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Button } from '@/components/ui/button';
import MoneySubnav from '@/components/yoyu-money/MoneySubnav.vue';
import { formatYen } from '@/lib/yoyuMoney/format';

type MonthlyRow = {
    year_month: string;
    amount_minor: string;
};

type CategoryRow = {
    category_id?: string | null;
    amount_minor: string;
};

type CounterpartyRow = {
    counterparty_id?: string | null;
    amount_minor: string;
};

interface Props {
    from: string;
    to: string;
    total_spend_minor: string;
    monthly: MonthlyRow[];
    by_category: CategoryRow[];
    by_counterparty: CounterpartyRow[];
}

const props = defineProps<Props>();

const filters = reactive({
    from: props.from.slice(0, 7),
    to: props.to.slice(0, 7),
});

function applyFilters(): void {
    router.get(
        '/yoyu/money/analysis',
        {
            from: filters.from,
            to: filters.to,
        },
        { preserveState: true, preserveScroll: true },
    );
}

defineOptions({
    layout: {
        title: 'ヨユウ',
        subtitle: '分析',
    },
});
</script>

<template>
    <div class="mx-auto max-w-[720px] space-y-4">
        <Head title="分析 — お金の余裕" />

        <MoneySubnav active="analysis" />

        <section
            class="rounded-[18px] border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <form
                class="flex flex-wrap items-end gap-3"
                @submit.prevent="applyFilters"
            >
                <label class="text-[12px] text-os-sub">
                    開始月
                    <input
                        v-model="filters.from"
                        type="month"
                        class="mt-1 block rounded-lg border border-os-line px-2 py-1.5 text-[13px] text-os-ink"
                    />
                </label>
                <label class="text-[12px] text-os-sub">
                    終了月
                    <input
                        v-model="filters.to"
                        type="month"
                        class="mt-1 block rounded-lg border border-os-line px-2 py-1.5 text-[13px] text-os-ink"
                    />
                </label>
                <Button type="submit" size="sm" variant="outline">適用</Button>
            </form>
            <div class="mt-4 rounded-xl bg-os-yoyu-soft/50 px-4 py-3">
                <p class="text-[12px] text-os-sub">期間支出合計</p>
                <p class="text-2xl font-bold text-os-ink">
                    {{ formatYen(total_spend_minor) }}
                </p>
                <p class="mt-1 text-[12px] text-os-sub">
                    {{ from }} 〜 {{ to }}
                </p>
            </div>
        </section>

        <section
            class="rounded-[18px] border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-3 text-sm font-bold text-os-ink">月次</h2>
            <p v-if="monthly.length === 0" class="text-[13px] text-os-sub">
                データがありません。
            </p>
            <table v-else class="w-full text-left text-[13px]">
                <thead class="text-[12px] text-os-sub">
                    <tr>
                        <th class="pb-2 font-semibold">月</th>
                        <th class="pb-2 text-right font-semibold">支出</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-os-line">
                    <tr v-for="row in monthly" :key="row.year_month">
                        <td class="py-2 text-os-ink">{{ row.year_month }}</td>
                        <td class="py-2 text-right font-semibold text-os-ink">
                            {{ formatYen(row.amount_minor) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section
            class="rounded-[18px] border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-3 text-sm font-bold text-os-ink">カテゴリ別</h2>
            <p
                v-if="by_category.length === 0"
                class="text-[13px] text-os-sub"
            >
                データがありません。
            </p>
            <table v-else class="w-full text-left text-[13px]">
                <thead class="text-[12px] text-os-sub">
                    <tr>
                        <th class="pb-2 font-semibold">カテゴリ</th>
                        <th class="pb-2 text-right font-semibold">支出</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-os-line">
                    <tr
                        v-for="(row, index) in by_category"
                        :key="row.category_id ?? `cat-${index}`"
                    >
                        <td class="py-2 text-os-ink">
                            {{ row.category_id ?? '(未分類)' }}
                        </td>
                        <td class="py-2 text-right font-semibold text-os-ink">
                            {{ formatYen(row.amount_minor) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section
            class="rounded-[18px] border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-3 text-sm font-bold text-os-ink">支払先別</h2>
            <p
                v-if="by_counterparty.length === 0"
                class="text-[13px] text-os-sub"
            >
                データがありません。
            </p>
            <table v-else class="w-full text-left text-[13px]">
                <thead class="text-[12px] text-os-sub">
                    <tr>
                        <th class="pb-2 font-semibold">支払先</th>
                        <th class="pb-2 text-right font-semibold">支出</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-os-line">
                    <tr
                        v-for="(row, index) in by_counterparty"
                        :key="row.counterparty_id ?? `cp-${index}`"
                    >
                        <td class="py-2 text-os-ink">
                            {{ row.counterparty_id ?? '(未設定)' }}
                        </td>
                        <td class="py-2 text-right font-semibold text-os-ink">
                            {{ formatYen(row.amount_minor) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>
