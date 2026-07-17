<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import MoneySubnav from '@/components/yoyu-money/MoneySubnav.vue';
import {
    formatSignedYen,
    formatYen,
    isNegativeMinor,
    isPositiveMinor,
    minorToDisplayString,
} from '@/lib/yoyuMoney/format';

type MoneySettings = {
    minimum_living_budget_minor: string | null;
    safety_buffer_minor: string | null;
    uncertain_outflow_reserve_bps: number;
    include_expected_income: boolean;
    calculation_horizon_months: number;
    formula_version: string;
    currency_code: string;
};

type AccountSummary = {
    id: string;
    name: string;
    type: string;
    current_balance_minor: string;
    available_balance_minor: string | null;
    balance_as_of: string | null;
    is_stale: boolean;
};

type Margin = {
    funds_minor: string;
    confirmed_income_minor: string;
    confirmed_outflow_minor: string;
    uncertain_reserve_minor: string;
    living_reserve_minor: string;
    safety_buffer_minor: string;
    projected_cash_minor: string;
    projected_margin_minor: string;
    safe_to_spend_minor: string;
    shortfall_minor: string;
    formula_version: string;
    as_of: string;
    horizon_end: string;
    is_complete: boolean;
    missing_settings: string[];
    warnings: string[];
    breakdown: Record<string, string | number | boolean | null>;
};

type UpcomingCashflow = {
    id: string;
    name: string;
    direction: string;
    kind: string;
    status: string;
    certainty: string;
    due_on: string;
    amount_minor: string;
    remaining_minor: string;
};

type FreshnessWarning = {
    account_id: string;
    account_name: string;
    balance_as_of: string | null;
    message: string;
};

interface Props {
    as_of: string;
    horizon_end: string;
    month: string;
    timezone: string;
    funds_minor: string;
    settings: MoneySettings;
    accounts: AccountSummary[];
    margin: Margin;
    upcoming_cashflows: UpcomingCashflow[];
    freshness_warnings: FreshnessWarning[];
}

const props = defineProps<Props>();

const heroMode = computed(() => {
    if (
        isNegativeMinor(props.margin.projected_margin_minor) ||
        isPositiveMinor(props.margin.shortfall_minor)
    ) {
        return 'shortfall' as const;
    }

    if (!props.margin.is_complete) {
        return 'incomplete' as const;
    }

    return 'safe' as const;
});

const breakdownRows = computed(() => {
    const labels: Array<{ key: string; label: string }> = [
        { key: 'funds_minor', label: '保有資金 (F)' },
        { key: 'confirmed_income_minor', label: '確定収入 (Ic)' },
        { key: 'confirmed_outflow_minor', label: '確定支出 (Oc)' },
        { key: 'uncertain_reserve_minor', label: '未確定支出予約 (Oe)' },
        { key: 'expected_outflow_gross_minor', label: '見込み支出総額' },
        { key: 'expected_income_minor', label: '見込み収入' },
        { key: 'living_input_minor', label: '最低生活費 (L)' },
        { key: 'safety_input_minor', label: '安全資金 (S)' },
        { key: 'essential_scheduled_minor', label: '必須予定（今月）' },
    ];

    return labels
        .map((row) => {
            const raw = props.margin.breakdown[row.key];
            const minor = minorToDisplayString(
                typeof raw === 'boolean' ? null : raw,
            );

            return {
                key: row.key,
                label: row.label,
                value: minor,
            };
        })
        .filter((row) => row.value !== null);
});

function freshnessLabel(message: string): string {
    if (message === 'balance_stale_over_7_days') {
        return '残高が7日以上更新されていません';
    }

    return message;
}

defineOptions({
    layout: {
        title: 'ヨユウ',
        subtitle: 'お金の余裕',
    },
});
</script>

<template>
    <div class="mx-auto max-w-[720px] space-y-4">
        <Head title="お金の余裕" />

        <MoneySubnav active="dashboard" />

        <section
            class="rounded-[18px] border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <div>
                    <p class="text-[12px] font-semibold text-os-sub">対象月</p>
                    <p class="text-lg font-bold text-os-ink">{{ month }}</p>
                </div>
                <div class="text-right text-[12px] text-os-sub">
                    <p>基準日 {{ as_of }}</p>
                    <p>投影終了 {{ horizon_end }}</p>
                    <p>{{ timezone }}</p>
                </div>
            </div>
            <ul
                v-if="freshness_warnings.length > 0"
                class="mt-3 space-y-1.5 rounded-xl bg-os-yoyu-soft/60 px-3 py-2 text-[12.5px] text-os-ink"
            >
                <li
                    v-for="warning in freshness_warnings"
                    :key="warning.account_id"
                >
                    {{ warning.account_name }}:
                    {{ freshnessLabel(warning.message) }}
                </li>
            </ul>
        </section>

        <section
            class="rounded-[18px] border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <p class="text-[12px] font-semibold text-os-sub">余裕の見通し</p>
            <template v-if="heroMode === 'shortfall'">
                <h2 class="mt-1 text-xl font-bold text-[#C05A48]">
                    不足見込み
                </h2>
                <p class="mt-2 text-3xl font-bold tracking-tight text-[#C05A48]">
                    {{ formatYen(margin.shortfall_minor) }}
                </p>
            </template>
            <template v-else-if="heroMode === 'incomplete'">
                <h2 class="mt-1 text-xl font-bold text-os-ink">
                    設定前の参考値
                </h2>
                <p class="mt-2 text-[13px] text-os-sub">
                    最低生活費または安全資金が未設定のため、断定表示はしません。
                </p>
                <p class="mt-2 text-3xl font-bold tracking-tight text-os-ink">
                    {{ formatSignedYen(margin.projected_margin_minor) }}
                </p>
                <Link
                    href="/yoyu/money/settings"
                    class="mt-3 inline-block text-[13px] font-semibold text-os-yoyu hover:underline"
                >
                    設定へ
                </Link>
            </template>
            <template v-else>
                <h2 class="mt-1 text-xl font-bold text-os-yoyu">
                    安全に使える余裕額
                </h2>
                <p class="mt-2 text-3xl font-bold tracking-tight text-os-yoyu">
                    {{ formatYen(margin.safe_to_spend_minor) }}
                </p>
            </template>
            <p class="mt-3 text-[13px] text-os-sub">
                投影余裕額
                <span class="font-semibold text-os-ink">
                    {{ formatSignedYen(margin.projected_margin_minor) }}
                </span>
            </p>
        </section>

        <section
            class="rounded-[18px] border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-3 text-sm font-bold text-os-ink">内訳</h2>
            <ul class="divide-y divide-os-line text-[13px]">
                <li
                    v-for="row in breakdownRows"
                    :key="row.key"
                    class="flex items-center justify-between gap-3 py-2"
                >
                    <span class="text-os-sub">{{ row.label }}</span>
                    <span class="font-semibold text-os-ink">
                        {{ formatYen(row.value) }}
                    </span>
                </li>
            </ul>
        </section>

        <section
            class="rounded-[18px] border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-3 text-sm font-bold text-os-ink">主要指標</h2>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-os-yoyu-soft/50 px-3 py-2.5">
                    <p class="text-[11px] text-os-sub">保有資金</p>
                    <p class="text-[15px] font-bold text-os-ink">
                        {{ formatYen(funds_minor) }}
                    </p>
                </div>
                <div class="rounded-xl bg-os-yoyu-soft/50 px-3 py-2.5">
                    <p class="text-[11px] text-os-sub">投影現金</p>
                    <p class="text-[15px] font-bold text-os-ink">
                        {{ formatYen(margin.projected_cash_minor) }}
                    </p>
                </div>
                <div class="rounded-xl bg-os-yoyu-soft/50 px-3 py-2.5">
                    <p class="text-[11px] text-os-sub">確定収入</p>
                    <p class="text-[15px] font-bold text-os-ink">
                        {{ formatYen(margin.confirmed_income_minor) }}
                    </p>
                </div>
                <div class="rounded-xl bg-os-yoyu-soft/50 px-3 py-2.5">
                    <p class="text-[11px] text-os-sub">確定支出</p>
                    <p class="text-[15px] font-bold text-os-ink">
                        {{ formatYen(margin.confirmed_outflow_minor) }}
                    </p>
                </div>
                <div class="rounded-xl bg-os-yoyu-soft/50 px-3 py-2.5">
                    <p class="text-[11px] text-os-sub">生活費予約</p>
                    <p class="text-[15px] font-bold text-os-ink">
                        {{ formatYen(margin.living_reserve_minor) }}
                    </p>
                </div>
                <div class="rounded-xl bg-os-yoyu-soft/50 px-3 py-2.5">
                    <p class="text-[11px] text-os-sub">安全資金</p>
                    <p class="text-[15px] font-bold text-os-ink">
                        {{ formatYen(margin.safety_buffer_minor) }}
                    </p>
                </div>
            </div>
            <p class="mt-3 text-[12px] text-os-sub">
                口座 {{ accounts.length }} 件 · 式バージョン
                {{ margin.formula_version }}
            </p>
        </section>

        <section
            class="rounded-[18px] border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-3 text-sm font-bold text-os-ink">
                直近7日の入出金予定
            </h2>
            <p
                v-if="upcoming_cashflows.length === 0"
                class="text-[13px] text-os-sub"
            >
                予定はありません。
            </p>
            <ul v-else class="divide-y divide-os-line">
                <li
                    v-for="item in upcoming_cashflows"
                    :key="item.id"
                    class="flex items-center justify-between gap-3 py-2.5 text-[13px]"
                >
                    <div>
                        <p class="font-semibold text-os-ink">{{ item.name }}</p>
                        <p class="text-[12px] text-os-sub">
                            {{ item.due_on }} · {{ item.direction }} ·
                            {{ item.certainty }}
                        </p>
                    </div>
                    <span
                        class="shrink-0 font-bold"
                        :class="
                            item.direction === 'inflow'
                                ? 'text-os-yoyu'
                                : 'text-os-ink'
                        "
                    >
                        {{ formatYen(item.remaining_minor) }}
                    </span>
                </li>
            </ul>
        </section>

        <section
            v-if="margin.warnings.length > 0 || margin.missing_settings.length > 0"
            class="rounded-[18px] border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-3 text-sm font-bold text-os-ink">注意・不足設定</h2>
            <ul class="space-y-1.5 text-[13px] text-os-sub">
                <li
                    v-for="setting in margin.missing_settings"
                    :key="`missing-${setting}`"
                >
                    未設定: {{ setting }}
                </li>
                <li
                    v-for="warning in margin.warnings"
                    :key="`warn-${warning}`"
                >
                    {{ warning }}
                </li>
            </ul>
        </section>
    </div>
</template>
