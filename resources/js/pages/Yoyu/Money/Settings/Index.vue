<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Button } from '@/components/ui/button';
import MoneySubnav from '@/components/yoyu-money/MoneySubnav.vue';
import { formatYen } from '@/lib/yoyuMoney/format';
import type { MoneyAmountDto, MoneyCategoryRow } from '@/lib/yoyuMoney/types';

type SettingsProps = {
    currency_code: string;
    minimum_living_budget: MoneyAmountDto | null;
    safety_buffer: MoneyAmountDto | null;
    uncertain_outflow_reserve_bps: number;
    include_expected_income: boolean;
    calculation_horizon_months: number;
    formula_version: string;
};

interface Props {
    settings: SettingsProps;
    timezone: string;
    categories: MoneyCategoryRow[];
}

const props = defineProps<Props>();

const form = reactive({
    minimum_living_budget_minor:
        props.settings.minimum_living_budget?.amountMinor ?? '',
    safety_buffer_minor: props.settings.safety_buffer?.amountMinor ?? '',
    timezone: props.timezone || 'Asia/Tokyo',
    include_expected_income: props.settings.include_expected_income,
    calculation_horizon_months: props.settings.calculation_horizon_months,
    uncertain_outflow_reserve_bps:
        props.settings.uncertain_outflow_reserve_bps,
});

const timezones = [
    'Asia/Tokyo',
    'UTC',
    'America/Los_Angeles',
    'Europe/London',
] as const;

function submit(): void {
    router.patch(
        '/yoyu/money/settings',
        {
            minimum_living_budget_minor:
                form.minimum_living_budget_minor || null,
            safety_buffer_minor: form.safety_buffer_minor || null,
            timezone: form.timezone,
            include_expected_income: form.include_expected_income,
            calculation_horizon_months: form.calculation_horizon_months,
            uncertain_outflow_reserve_bps: form.uncertain_outflow_reserve_bps,
        },
        { preserveScroll: true },
    );
}

defineOptions({
    layout: {
        title: 'ヨユウ',
        subtitle: 'お金の設定',
    },
});
</script>

<template>
    <div class="mx-auto max-w-[720px] space-y-4">
        <Head title="設定 — お金の余裕" />

        <MoneySubnav active="settings" />

        <section
            class="rounded-[18px] border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-3 text-sm font-bold text-os-ink">余裕の前提</h2>
            <form class="space-y-3" @submit.prevent="submit">
                <div class="flex flex-wrap gap-3">
                    <label class="text-[12px] text-os-sub">
                        最低生活費（円）
                        <input
                            v-model="form.minimum_living_budget_minor"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            class="mt-1 block w-36 rounded-lg border border-os-line px-2 py-1.5 text-[13px] text-os-ink"
                        />
                    </label>
                    <label class="text-[12px] text-os-sub">
                        安全資金（円）
                        <input
                            v-model="form.safety_buffer_minor"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            class="mt-1 block w-36 rounded-lg border border-os-line px-2 py-1.5 text-[13px] text-os-ink"
                        />
                    </label>
                    <label class="text-[12px] text-os-sub">
                        投影期間（月）
                        <input
                            v-model.number="form.calculation_horizon_months"
                            type="number"
                            min="1"
                            max="24"
                            class="mt-1 block w-24 rounded-lg border border-os-line px-2 py-1.5 text-[13px] text-os-ink"
                        />
                    </label>
                </div>
                <label class="block text-[12px] text-os-sub">
                    タイムゾーン
                    <select
                        v-model="form.timezone"
                        class="mt-1 block w-56 rounded-lg border border-os-line px-2 py-1.5 text-[13px] text-os-ink"
                    >
                        <option
                            v-for="tz in timezones"
                            :key="tz"
                            :value="tz"
                        >
                            {{ tz }}
                        </option>
                    </select>
                </label>
                <label class="flex items-center gap-2 text-[13px] text-os-ink">
                    <input
                        v-model="form.include_expected_income"
                        type="checkbox"
                    />
                    見込み収入を余裕計算に含める
                </label>
                <p class="text-[12px] text-os-sub">
                    現在値: 生活費
                    {{ formatYen(settings.minimum_living_budget?.amountMinor) }}
                    · 安全資金
                    {{ formatYen(settings.safety_buffer?.amountMinor) }} · 通貨
                    {{ settings.currency_code }} · 式
                    {{ settings.formula_version }}
                </p>
                <Button type="submit" size="sm" class="rounded-full">
                    保存
                </Button>
            </form>
        </section>

        <section
            class="rounded-[18px] border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-3 text-sm font-bold text-os-ink">カテゴリ</h2>
            <p v-if="categories.length === 0" class="text-[13px] text-os-sub">
                カテゴリがありません。
            </p>
            <ul v-else class="divide-y divide-os-line text-[13px]">
                <li
                    v-for="category in categories"
                    :key="category.id"
                    class="flex flex-wrap items-center justify-between gap-2 py-2"
                >
                    <span class="font-semibold text-os-ink">
                        {{ category.name }}
                        <span
                            v-if="category.is_essential"
                            class="ml-1 text-[11px] text-os-yoyu"
                        >
                            必須
                        </span>
                    </span>
                    <span class="text-[12px] text-os-sub">
                        {{ category.direction_scope }} ·
                        {{ category.flexibility_default }}
                        <span v-if="!category.is_active"> · 停止</span>
                    </span>
                </li>
            </ul>
        </section>

        <section
            class="rounded-[18px] border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-2 text-sm font-bold text-os-ink">エクスポート</h2>
            <p class="mb-3 text-[13px] text-os-sub">
                お金の余裕データをZIPでダウンロードします。
            </p>
            <Button as="a" href="/yoyu/money/export" size="sm" variant="outline">
                エクスポート
            </Button>
        </section>
    </div>
</template>
