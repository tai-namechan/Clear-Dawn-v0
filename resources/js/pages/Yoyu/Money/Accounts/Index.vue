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
import { accountTypeLabel } from '@/lib/yoyuMoney/labels';
import { formatYen } from '@/lib/yoyuMoney/format';
import { moneyAssetsTabs } from '@/lib/yoyuMoney/navigation';
import type { MoneyAccountRow } from '@/lib/yoyuMoney/types';

interface Props {
    accounts: MoneyAccountRow[];
}

const props = defineProps<Props>();

const page = usePage();
const drawerOpen = ref(false);

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
                drawerOpen.value = false;
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

onMounted(() => {
    const url = page.url;
    if (url.includes('compose=1')) {
        drawerOpen.value = true;
    }
});

defineOptions({
    layout: {
        title: 'ヨユウ',
        subtitle: '口座',
    },
});
</script>

<template>
    <MoneyPageShell
        title="口座"
        :section-tabs="moneyAssetsTabs"
        section-active="accounts"
        section-label="資産・返済"
        primary-active="assets"
    >
        <template #actions>
            <Button type="button" class="rounded-lg" @click="drawerOpen = true">
                ＋口座を追加
            </Button>
        </template>

        <MoneyEmptyState
            v-if="accounts.length === 0"
            title="口座がまだありません"
            description="口座を追加すると、残高の合計から余裕額を計算できます。"
            action-label="口座を追加"
            action-href="/yoyu/money/accounts?compose=1"
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
                            <th class="px-4 py-2.5 font-semibold">口座名</th>
                            <th class="px-4 py-2.5 font-semibold">種別</th>
                            <th class="px-4 py-2.5 text-right font-semibold">残高</th>
                            <th class="px-4 py-2.5 font-semibold">残高を更新</th>
                            <th class="px-4 py-2.5 font-semibold">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="account in accounts"
                            :key="account.id"
                            class="border-b border-os-line/80"
                            :class="!account.is_active ? 'opacity-50' : ''"
                        >
                            <td class="px-4 py-3">
                                <p class="font-semibold text-os-ink">
                                    {{ account.name }}
                                    <span
                                        v-if="!account.is_active"
                                        class="ml-1 text-[11px] font-normal text-os-sub"
                                    >
                                        停止中
                                    </span>
                                </p>
                                <p class="text-[11px] text-os-faint">
                                    {{ account.currency_code }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-os-sub">
                                {{ accountTypeLabel(account.type) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <p class="font-bold text-os-ink">
                                    {{ formatYen(account.current_balance.amountMinor) }}
                                </p>
                                <p
                                    v-if="account.available_balance"
                                    class="text-[11px] text-os-faint"
                                >
                                    利用可能 {{ formatYen(account.available_balance.amountMinor) }}
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                <form
                                    class="flex items-center gap-2"
                                    @submit.prevent="submitBalance(account)"
                                >
                                    <input
                                        :value="draftFor(account)"
                                        type="text"
                                        inputmode="numeric"
                                        pattern="-?[0-9]*"
                                        required
                                        class="w-28 rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                                        @input="
                                            balanceDrafts[account.id] = (
                                                $event.target as HTMLInputElement
                                            ).value
                                        "
                                    />
                                    <Button type="submit" size="sm" variant="outline">
                                        更新
                                    </Button>
                                </form>
                            </td>
                            <td class="px-4 py-3">
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    @click="toggleActive(account)"
                                >
                                    {{ account.is_active ? '停止' : '有効化' }}
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards -->
            <ul class="divide-y divide-os-line md:hidden">
                <li
                    v-for="account in accounts"
                    :key="`m-${account.id}`"
                    class="space-y-3 p-4"
                    :class="!account.is_active ? 'opacity-50' : ''"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold text-os-ink">
                                {{ account.name }}
                                <span
                                    v-if="!account.is_active"
                                    class="ml-1 text-[11px] font-normal text-os-sub"
                                >
                                    停止中
                                </span>
                            </p>
                            <p class="text-[12px] text-os-sub">
                                {{ accountTypeLabel(account.type) }} · {{ account.currency_code }}
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-os-ink">
                                {{ formatYen(account.current_balance.amountMinor) }}
                            </p>
                            <p v-if="account.available_balance" class="text-[11px] text-os-faint">
                                利用可能 {{ formatYen(account.available_balance.amountMinor) }}
                            </p>
                        </div>
                    </div>
                    <form
                        class="flex flex-wrap items-end gap-2"
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
                        <Button type="submit" size="sm" variant="outline">更新</Button>
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

        <Sheet :open="drawerOpen" @update:open="drawerOpen = $event">
            <SheetContent side="right" class="w-full border-os-line bg-white sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>口座を追加</SheetTitle>
                    <SheetDescription>
                        残高は後からいつでも更新できます。
                    </SheetDescription>
                </SheetHeader>
                <form class="mt-4 space-y-3 px-1" @submit.prevent="submitCreate">
                    <label class="block text-[12px] text-os-sub">
                        口座名
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
                            <option value="bank">銀行</option>
                            <option value="cash">現金</option>
                            <option value="electronic_money">電子マネー</option>
                            <option value="other">その他</option>
                        </select>
                    </label>
                    <label class="block text-[12px] text-os-sub">
                        現在の残高（円・整数）
                        <input
                            v-model="createForm.current_balance_minor"
                            type="text"
                            inputmode="numeric"
                            pattern="-?[0-9]*"
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
