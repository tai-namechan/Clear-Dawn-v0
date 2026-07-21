<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Button } from '@/components/ui/button';
import MoneyPageShell from '@/components/yoyu-money/MoneyPageShell.vue';
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
    uncertain_outflow_reserve_bps: props.settings.uncertain_outflow_reserve_bps,
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
    <MoneyPageShell
        title="設定"
        primary-active="settings"
        :show-record-menu="false"
    >
        <p class="text-[12px] text-os-sub">
            現在は日本円（JPY）に対応しています。設定した値はホーム画面の余裕計算にリアルタイムで反映されます。
        </p>

        <!-- 余裕の計算 -->
        <section
            class="rounded-2xl border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-1 text-sm font-bold text-os-ink">余裕の計算</h2>
            <p class="mb-3 text-[12px] text-os-sub">
                「安全に使える金額」の算出基準を設定します。設定後、ホーム画面の数字が更新されます。
            </p>
            <form class="space-y-4" @submit.prevent="submit">
                <div class="flex flex-wrap gap-4">
                    <label class="text-[12px] text-os-sub">
                        最低生活費（円）
                        <input
                            v-model="form.minimum_living_budget_minor"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            class="mt-1 block w-36 rounded-lg border border-os-line px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                        />
                        <p class="mt-0.5 text-[11px] text-os-faint">
                            現在:
                            {{
                                formatYen(
                                    settings.minimum_living_budget?.amountMinor,
                                )
                            }}
                        </p>
                    </label>
                    <label class="text-[12px] text-os-sub">
                        安全資金（円）
                        <input
                            v-model="form.safety_buffer_minor"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            class="mt-1 block w-36 rounded-lg border border-os-line px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                        />
                        <p class="mt-0.5 text-[11px] text-os-faint">
                            現在:
                            {{ formatYen(settings.safety_buffer?.amountMinor) }}
                        </p>
                    </label>
                    <label class="text-[12px] text-os-sub">
                        投影期間（月）
                        <input
                            v-model.number="form.calculation_horizon_months"
                            type="number"
                            min="1"
                            max="24"
                            class="mt-1 block w-24 rounded-lg border border-os-line px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                        />
                    </label>
                </div>
                <label class="flex items-center gap-2 text-[13px] text-os-ink">
                    <input
                        v-model="form.include_expected_income"
                        type="checkbox"
                    />
                    見込み収入を余裕計算に含める
                </label>
                <p class="text-[12px] text-os-faint">
                    通貨: {{ settings.currency_code }} · 計算式バージョン:
                    {{ settings.formula_version }}
                </p>
                <Button type="submit" size="sm" class="rounded-full"
                    >保存</Button
                >
            </form>
        </section>

        <!-- カテゴリ -->
        <section
            class="rounded-2xl border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-1 text-sm font-bold text-os-ink">カテゴリ</h2>
            <p class="mb-3 text-[12px] text-os-sub">
                取引に設定できるカテゴリの一覧です。
            </p>
            <p v-if="categories.length === 0" class="text-[13px] text-os-sub">
                カテゴリがありません。
            </p>
            <div
                v-else
                class="overflow-hidden rounded-xl border border-os-line"
            >
                <table class="min-w-full text-left text-[13px]">
                    <thead
                        class="border-b border-os-line bg-os-yoyu-bg/80 text-os-sub"
                    >
                        <tr>
                            <th class="px-4 py-2.5 font-semibold">名前</th>
                            <th class="px-4 py-2.5 font-semibold">方向</th>
                            <th class="px-4 py-2.5 font-semibold">柔軟性</th>
                            <th class="px-4 py-2.5 font-semibold">状態</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-os-line">
                        <tr
                            v-for="category in categories"
                            :key="category.id"
                            :class="!category.is_active ? 'opacity-50' : ''"
                        >
                            <td class="px-4 py-2.5">
                                <span class="font-semibold text-os-ink">
                                    {{ category.name }}
                                </span>
                                <span
                                    v-if="category.is_essential"
                                    class="ml-1 text-[11px] text-os-yoyu"
                                >
                                    必須
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-os-sub">
                                {{ category.direction_scope }}
                            </td>
                            <td class="px-4 py-2.5 text-os-sub">
                                {{ category.flexibility_default }}
                            </td>
                            <td class="px-4 py-2.5 text-[12px] text-os-faint">
                                {{ category.is_active ? '有効' : '停止' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- 表示・基本設定 -->
        <section
            class="rounded-2xl border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-1 text-sm font-bold text-os-ink">表示・基本設定</h2>
            <p class="mb-3 text-[12px] text-os-sub">
                日付の基準に使用するタイムゾーンを設定します。
            </p>
            <form class="space-y-3" @submit.prevent="submit">
                <label class="block text-[12px] text-os-sub">
                    タイムゾーン
                    <select
                        v-model="form.timezone"
                        class="mt-1 block w-56 rounded-lg border border-os-line px-2 py-1.5 text-[13px] text-os-ink"
                    >
                        <option v-for="tz in timezones" :key="tz" :value="tz">
                            {{ tz }}
                        </option>
                    </select>
                </label>
                <Button type="submit" size="sm" class="rounded-full"
                    >保存</Button
                >
            </form>
        </section>

        <!-- データ -->
        <section
            class="rounded-2xl border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-1 text-sm font-bold text-os-ink">データ</h2>
            <p class="mb-3 text-[13px] text-os-sub">
                お金の余裕データをZIPでダウンロードします。
            </p>
            <Button
                as="a"
                href="/yoyu/money/export"
                size="sm"
                variant="outline"
            >
                エクスポート
            </Button>
        </section>
    </MoneyPageShell>
</template>
