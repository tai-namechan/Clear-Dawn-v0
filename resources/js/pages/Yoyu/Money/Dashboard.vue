<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdjustmentCandidateCard from '@/components/yoyu-money/AdjustmentCandidateCard.vue';
import CashflowTimeline from '@/components/yoyu-money/CashflowTimeline.vue';
import MoneyAmount from '@/components/yoyu-money/MoneyAmount.vue';
import MoneyPageShell from '@/components/yoyu-money/MoneyPageShell.vue';
import MoneySetupGuide from '@/components/yoyu-money/MoneySetupGuide.vue';
import MoneyStatusBadge from '@/components/yoyu-money/MoneyStatusBadge.vue';
import MoneySummaryCard from '@/components/yoyu-money/MoneySummaryCard.vue';
import { formatYen, isPositiveMinor } from '@/lib/yoyuMoney/format';
import { missingSettingLabel } from '@/lib/yoyuMoney/labels';

type MoneySettings = {
    minimum_living_budget_minor: string | null;
    safety_buffer_minor: string | null;
    uncertain_outflow_reserve_bps: number;
    include_expected_income: boolean;
    calculation_horizon_months: number;
    formula_version: string;
    currency_code: string;
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

type SetupStep = {
    key: string;
    label: string;
    description: string;
    status: 'complete' | 'incomplete' | 'optional';
    href: string;
    required: boolean;
};

type SetupProgress = {
    is_complete: boolean;
    required_complete: boolean;
    completed_required_count: number;
    required_count: number;
    next_step_key: string | null;
    steps: SetupStep[];
};

type TimelineEvent = {
    id: string;
    due_on: string;
    name: string;
    direction: string;
    amount_minor: string;
    signed_amount_minor: string;
    balance_after_minor: string;
    is_shortfall: boolean;
    flexibility: string;
    certainty: string;
};

type UpcomingPayment = {
    id: string;
    name: string;
    direction: string;
    kind: string;
    status: string;
    certainty: string;
    flexibility: string;
    due_on: string;
    amount_minor: string;
    remaining_minor: string;
    balance_after_minor: string | null;
    counterparty_name: string | null;
    is_adjustable: boolean;
    lock_version: number;
};

type Candidate = {
    id: string;
    type: string;
    title: string;
    detail: string;
    amount_minor: string | null;
    href: string;
    simulate_href: string;
};

type DebtSummary = {
    outstanding_debt_minor: string;
    monthly_repayment_minor: string;
    card_statement_minor: string;
    next_repayment_on: string | null;
    credit_available_minor: string | null;
    credit_limit_minor: string | null;
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
    month_end?: string;
    timezone: string;
    funds_minor: string;
    settings: MoneySettings;
    accounts: Array<Record<string, unknown>>;
    margin: Margin;
    upcoming_cashflows: Array<Record<string, unknown>>;
    upcoming_payments?: UpcomingPayment[];
    freshness_warnings: FreshnessWarning[];
    setup_progress?: SetupProgress;
    balance_timeline?: TimelineEvent[];
    month_end_balance_minor?: string;
    next_income?: {
        id: string;
        name: string;
        due_on: string;
        amount_minor: string;
    } | null;
    first_shortfall_date?: string | null;
    month_summary?: {
        income_minor: string;
        confirmed_outflow_minor: string;
        expected_outflow_minor: string;
    };
    debt_summary?: DebtSummary;
    adjustment_candidates?: Candidate[];
}

const props = defineProps<Props>();

const SETUP_DISMISS_KEY = 'yoyu-money-setup-dismissed';

const setupDismissed = ref(
    typeof window !== 'undefined' &&
        window.localStorage.getItem(SETUP_DISMISS_KEY) === '1',
);

const showBreakdown = ref(false);

const showSetup = computed(
    () =>
        props.setup_progress !== undefined &&
        !props.setup_progress.required_complete &&
        !setupDismissed.value,
);

const payments = computed(() => props.upcoming_payments ?? []);
const timeline = computed(() => props.balance_timeline ?? []);
const candidates = computed(() => props.adjustment_candidates ?? []);
const debt = computed(
    () =>
        props.debt_summary ?? {
            outstanding_debt_minor: '0',
            monthly_repayment_minor: '0',
            card_statement_minor: '0',
            next_repayment_on: null,
            credit_available_minor: null,
            credit_limit_minor: null,
        },
);
const monthSummary = computed(
    () =>
        props.month_summary ?? {
            income_minor: props.margin.confirmed_income_minor,
            confirmed_outflow_minor: props.margin.confirmed_outflow_minor,
            expected_outflow_minor: props.margin.uncertain_reserve_minor,
        },
);

const hasShortfall = computed(() =>
    isPositiveMinor(props.margin.shortfall_minor),
);

function dismissSetup(): void {
    setupDismissed.value = true;
    window.localStorage.setItem(SETUP_DISMISS_KEY, '1');
}

function settlePayment(payment: UpcomingPayment): void {
    router.post(
        `/yoyu/money/cashflows/${payment.id}/settle`,
        {
            amount_minor: payment.remaining_minor,
            occurred_on: payment.due_on,
            create_transaction: true,
            update_balance: true,
            lock_version: payment.lock_version,
        },
        { preserveScroll: true },
    );
}

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
    <MoneyPageShell
        title="ホーム"
        document-title="お金の余裕"
        :month="month"
        :as-of="as_of"
        show-month-switcher
        primary-active="home"
    >
        <MoneySetupGuide
            v-if="showSetup && setup_progress"
            :steps="setup_progress.steps"
            :next-step-key="setup_progress.next_step_key"
            :completed-required-count="setup_progress.completed_required_count"
            :required-count="setup_progress.required_count"
            @dismiss="dismissSetup"
        />

        <ul
            v-if="freshness_warnings.length > 0"
            class="space-y-1.5 rounded-xl bg-os-yoyu-soft/60 px-3 py-2 text-[12.5px] text-os-ink"
        >
            <li v-for="warning in freshness_warnings" :key="warning.account_id">
                {{ warning.account_name }}:
                {{ freshnessLabel(warning.message) }}
            </li>
        </ul>

        <!-- Hero: 安全に使える金額 -->
        <section
            class="rounded-2xl border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)] md:p-6"
        >
            <p class="text-[12px] font-semibold text-os-sub">
                今月、安全に使える金額
            </p>

            <template v-if="!margin.is_complete">
                <p class="mt-2 text-[13px] text-os-sub">
                    計算に必要な設定がまだ完了していません。参考値として投影余裕額を表示しています。
                </p>
                <p
                    class="mt-2 text-3xl font-bold tracking-tight text-os-faint md:text-4xl"
                >
                    <MoneyAmount
                        :amount-minor="margin.projected_margin_minor"
                        signed
                    />
                </p>
                <p class="mt-2 text-[12px] text-os-sub">
                    未設定:
                    {{
                        margin.missing_settings
                            .map((key) => missingSettingLabel(key))
                            .join('、')
                    }}
                </p>
                <Link
                    href="/yoyu/money/settings"
                    class="mt-2 inline-block text-[13px] font-semibold text-os-yoyu hover:underline"
                >
                    設定へ進む
                </Link>
            </template>
            <template v-else-if="hasShortfall">
                <p
                    class="mt-2 text-3xl font-bold tracking-tight text-[#8A5A3B] md:text-4xl"
                >
                    {{ formatYen('0') }}
                </p>
                <p class="mt-2 text-[13px] text-[#8A5A3B]">
                    今月は
                    <MoneyAmount :amount-minor="margin.shortfall_minor" />
                    不足する見込みです
                </p>
            </template>
            <template v-else>
                <p
                    class="mt-2 text-3xl font-bold tracking-tight text-os-yoyu md:text-right md:text-4xl"
                >
                    {{ formatYen(margin.safe_to_spend_minor) }}
                </p>
            </template>

            <div
                class="mt-4 grid gap-3 border-t border-os-line pt-4 text-[13px] sm:grid-cols-2"
            >
                <div>
                    <p class="text-os-sub">月末予測残高</p>
                    <p class="font-semibold text-os-ink">
                        <MoneyAmount
                            :amount-minor="
                                month_end_balance_minor ??
                                margin.projected_cash_minor
                            "
                        />
                    </p>
                </div>
                <div>
                    <p class="text-os-sub">次の入金予定</p>
                    <p v-if="next_income" class="font-semibold text-os-ink">
                        {{ next_income.due_on }} {{ next_income.name }}
                        <MoneyAmount
                            :amount-minor="next_income.amount_minor"
                            signed
                        />
                    </p>
                    <p v-else class="text-os-faint">登録なし</p>
                </div>
                <div class="sm:col-span-2">
                    <p class="text-os-sub">
                        計算に含めた期間：{{ as_of }}〜{{ horizon_end }}
                    </p>
                </div>
            </div>

            <button
                type="button"
                class="mt-3 text-[12px] font-semibold text-os-yoyu hover:underline focus-visible:ring-2 focus-visible:ring-os-yoyu/40 focus-visible:outline-none"
                @click="showBreakdown = !showBreakdown"
            >
                {{ showBreakdown ? '計算の内訳を閉じる' : '計算の内訳を見る' }}
            </button>
            <dl
                v-if="showBreakdown"
                class="mt-3 grid gap-2 rounded-xl bg-os-yoyu-bg/70 p-3 text-[12px] sm:grid-cols-2"
            >
                <div class="flex justify-between gap-2">
                    <dt class="text-os-sub">現在の資金</dt>
                    <dd class="font-semibold">
                        <MoneyAmount :amount-minor="margin.funds_minor" />
                    </dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-os-sub">確定の入金予定</dt>
                    <dd class="font-semibold">
                        <MoneyAmount
                            :amount-minor="margin.confirmed_income_minor"
                        />
                    </dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-os-sub">確定の支払い</dt>
                    <dd class="font-semibold">
                        <MoneyAmount
                            :amount-minor="margin.confirmed_outflow_minor"
                        />
                    </dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-os-sub">見込み支出の予約</dt>
                    <dd class="font-semibold">
                        <MoneyAmount
                            :amount-minor="margin.uncertain_reserve_minor"
                        />
                    </dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-os-sub">最低生活費の残り</dt>
                    <dd class="font-semibold">
                        <MoneyAmount
                            :amount-minor="margin.living_reserve_minor"
                        />
                    </dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-os-sub">安全資金</dt>
                    <dd class="font-semibold">
                        <MoneyAmount
                            :amount-minor="margin.safety_buffer_minor"
                        />
                    </dd>
                </div>
            </dl>
        </section>

        <!-- Summary row -->
        <section class="grid gap-3 sm:grid-cols-3">
            <MoneySummaryCard
                label="現在の資金"
                :amount-minor="funds_minor"
                hint="口座残高の合計（カード枠は含みません）"
            />
            <MoneySummaryCard
                label="今月の収入"
                :amount-minor="monthSummary.income_minor"
            />
            <MoneySummaryCard
                label="支払い予定"
                :amount-minor="monthSummary.confirmed_outflow_minor"
                :hint="
                    isPositiveMinor(monthSummary.expected_outflow_minor)
                        ? `見込み支出 ${formatYen(monthSummary.expected_outflow_minor)}`
                        : null
                "
            />
        </section>

        <section class="grid gap-3 sm:grid-cols-3">
            <MoneySummaryCard
                label="借入総残高"
                :amount-minor="debt.outstanding_debt_minor"
                emphasis="muted"
            />
            <MoneySummaryCard
                label="毎月の返済額"
                :amount-minor="debt.monthly_repayment_minor"
                emphasis="muted"
            />
            <MoneySummaryCard
                label="カード請求予定"
                :amount-minor="debt.card_statement_minor"
                emphasis="muted"
                :hint="
                    debt.credit_available_minor
                        ? `信用枠の利用可能額 ${formatYen(debt.credit_available_minor)}（資金には加算していません）`
                        : null
                "
            />
        </section>

        <CashflowTimeline :events="timeline" />

        <div class="grid gap-4 lg:grid-cols-2">
            <section
                class="rounded-2xl border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
            >
                <h2 class="mb-3 text-sm font-bold text-os-ink">
                    もうすぐ支払うもの
                </h2>
                <p v-if="payments.length === 0" class="text-[13px] text-os-sub">
                    直近の支払い予定はありません。
                </p>
                <ul v-else class="divide-y divide-os-line">
                    <li
                        v-for="payment in payments.slice(0, 6)"
                        :key="payment.id"
                        class="py-3"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-os-ink">
                                    {{ payment.name }}
                                </p>
                                <p class="text-[12px] text-os-sub">
                                    {{ payment.due_on }}
                                    <template v-if="payment.counterparty_name">
                                        · {{ payment.counterparty_name }}
                                    </template>
                                </p>
                                <p
                                    v-if="payment.balance_after_minor"
                                    class="text-[12px] text-os-faint"
                                >
                                    支払後残高
                                    <MoneyAmount
                                        :amount-minor="
                                            payment.balance_after_minor
                                        "
                                    />
                                </p>
                            </div>
                            <p class="shrink-0 text-right font-bold">
                                <MoneyAmount
                                    :amount-minor="payment.remaining_minor"
                                />
                            </p>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-lg border border-os-line px-2.5 py-1.5 text-[12px] font-semibold text-os-sub hover:bg-os-yoyu-soft focus-visible:ring-2 focus-visible:ring-os-yoyu/40 focus-visible:outline-none"
                                @click="settlePayment(payment)"
                            >
                                支払い済みにする
                            </button>
                            <Link
                                :href="`/yoyu/money/cashflows?highlight=${payment.id}`"
                                class="rounded-lg border border-os-line px-2.5 py-1.5 text-[12px] font-semibold text-os-sub hover:bg-os-yoyu-soft"
                            >
                                詳細
                            </Link>
                            <Link
                                v-if="payment.is_adjustable"
                                :href="`/yoyu/money/simulations?from_cashflow=${payment.id}`"
                                class="rounded-lg border border-os-line px-2.5 py-1.5 text-[12px] font-semibold text-os-yoyu hover:bg-os-yoyu-soft"
                            >
                                調整を比較
                            </Link>
                        </div>
                    </li>
                </ul>
            </section>

            <section
                class="rounded-2xl border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
            >
                <h2 class="mb-3 text-sm font-bold text-os-ink">今月の注意点</h2>
                <ul class="space-y-2 text-[13px] text-os-sub">
                    <li v-if="hasShortfall">
                        <MoneyStatusBadge label="不足見込み" tone="caution" />
                        <span class="ml-2">
                            今月は
                            <MoneyAmount
                                :amount-minor="margin.shortfall_minor"
                            />
                            不足する見込みです。支払いの調整や収入の確認を検討できます。
                        </span>
                    </li>
                    <li v-if="first_shortfall_date">
                        <MoneyStatusBadge label="残高推移" tone="caution" />
                        <span class="ml-2">
                            {{
                                first_shortfall_date
                            }}
                            時点で残高が不足する見込みです。
                        </span>
                    </li>
                    <li v-if="!margin.is_complete">
                        <MoneyStatusBadge label="設定不足" tone="info" />
                        <span class="ml-2">
                            最低生活費または安全資金が未設定のため、断定表示はしていません。
                        </span>
                    </li>
                    <li
                        v-if="
                            !hasShortfall &&
                            margin.is_complete &&
                            !first_shortfall_date
                        "
                    >
                        現在の登録内容では、目立った不足見込みはありません。
                    </li>
                    <li>
                        <Link
                            href="/yoyu/money/simulations"
                            class="font-semibold text-os-yoyu hover:underline"
                        >
                            シミュレーターで比較する →
                        </Link>
                    </li>
                </ul>
            </section>
        </div>

        <AdjustmentCandidateCard :candidates="candidates" />
    </MoneyPageShell>
</template>
