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
import { formatYen } from '@/lib/yoyuMoney/format';
import { moneyAssetsTabs } from '@/lib/yoyuMoney/navigation';
import type { MoneyCardRow } from '@/lib/yoyuMoney/types';

interface Props {
    cards: MoneyCardRow[];
}

defineProps<Props>();

const page = usePage();
const drawerOpen = ref(false);

const createForm = reactive({
    name: '',
    issuer_name: '',
    closing_day: '15',
    payment_day: '10',
    current_statement_minor: '',
    unconfirmed_minor: '',
});

function submitCreate(): void {
    router.post(
        '/yoyu/money/cards',
        {
            name: createForm.name,
            issuer_name: createForm.issuer_name || null,
            closing_day: createForm.closing_day,
            payment_day: createForm.payment_day,
            current_statement_minor: createForm.current_statement_minor || null,
            unconfirmed_minor: createForm.unconfirmed_minor || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                createForm.name = '';
                createForm.issuer_name = '';
                createForm.current_statement_minor = '';
                createForm.unconfirmed_minor = '';
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
        subtitle: 'カード',
    },
});
</script>

<template>
    <MoneyPageShell
        title="カード"
        :section-tabs="moneyAssetsTabs"
        section-active="cards"
        section-label="資産・返済"
        primary-active="assets"
    >
        <template #actions>
            <Button type="button" class="rounded-lg" @click="drawerOpen = true">
                ＋カードを追加
            </Button>
        </template>

        <p class="text-[12px] text-os-sub">
            カードの信用枠は保有資金に加算されません。月次支払い額のみ余裕計算に影響します。
        </p>

        <MoneyEmptyState
            v-if="cards.length === 0"
            title="カードがまだありません"
            description="クレジットカードを追加すると、当月請求・未確定利用を把握できます。"
            action-label="カードを追加"
            action-href="/yoyu/money/cards?compose=1"
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
                            <th class="px-4 py-2.5 font-semibold">カード名</th>
                            <th class="px-4 py-2.5 font-semibold">
                                締日 / 支払日
                            </th>
                            <th class="px-4 py-2.5 text-right font-semibold">
                                当月請求
                            </th>
                            <th class="px-4 py-2.5 text-right font-semibold">
                                未確定利用
                            </th>
                            <th class="px-4 py-2.5 font-semibold">
                                信用枠情報
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="card in cards"
                            :key="card.id"
                            class="border-b border-os-line/80"
                            :class="!card.is_active ? 'opacity-50' : ''"
                        >
                            <td class="px-4 py-3">
                                <p class="font-semibold text-os-ink">
                                    {{ card.name }}
                                </p>
                                <p class="text-[11px] text-os-faint">
                                    {{ card.issuer_name || '発行元未設定' }}
                                    <span v-if="!card.is_active" class="ml-1"
                                        >· 停止中</span
                                    >
                                </p>
                            </td>
                            <td class="px-4 py-3 text-os-sub">
                                {{ card.closing_day }}日締 /
                                {{ card.payment_day }}日払い
                            </td>
                            <td
                                class="px-4 py-3 text-right font-semibold text-os-ink"
                            >
                                {{
                                    formatYen(
                                        card.current_statement?.amountMinor,
                                    )
                                }}
                            </td>
                            <td class="px-4 py-3 text-right text-os-sub">
                                {{ formatYen(card.unconfirmed?.amountMinor) }}
                            </td>
                            <td class="px-4 py-3 text-[12px] text-os-faint">
                                <template v-if="card.available">
                                    利用可能枠
                                    {{ formatYen(card.available.amountMinor) }}
                                </template>
                                <template v-else>—</template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Mobile cards -->
            <ul class="divide-y divide-os-line md:hidden">
                <li
                    v-for="card in cards"
                    :key="`m-${card.id}`"
                    class="space-y-2 p-4"
                    :class="!card.is_active ? 'opacity-50' : ''"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-semibold text-os-ink">
                                {{ card.name }}
                            </p>
                            <p class="text-[12px] text-os-sub">
                                {{ card.issuer_name || '発行元未設定' }} ·
                                {{ card.closing_day }}日締 ·
                                {{ card.payment_day }}日払い
                                <span v-if="!card.is_active"> · 停止中</span>
                            </p>
                        </div>
                    </div>
                    <dl class="grid grid-cols-2 gap-2 text-[13px]">
                        <div>
                            <dt class="text-[11px] text-os-sub">当月請求</dt>
                            <dd class="font-semibold text-os-ink">
                                {{
                                    formatYen(
                                        card.current_statement?.amountMinor,
                                    )
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-os-sub">未確定利用</dt>
                            <dd class="text-os-sub">
                                {{ formatYen(card.unconfirmed?.amountMinor) }}
                            </dd>
                        </div>
                    </dl>
                    <p v-if="card.available" class="text-[12px] text-os-faint">
                        信用枠情報 — 利用可能枠
                        {{ formatYen(card.available.amountMinor) }}
                    </p>
                </li>
            </ul>
        </section>

        <Sheet :open="drawerOpen" @update:open="drawerOpen = $event">
            <SheetContent
                side="right"
                class="w-full border-os-line bg-white sm:max-w-md"
            >
                <SheetHeader>
                    <SheetTitle>カードを追加</SheetTitle>
                    <SheetDescription>
                        カードの信用枠は資金総額に含みません。請求額が余裕計算に反映されます。
                    </SheetDescription>
                </SheetHeader>
                <form
                    class="mt-4 space-y-3 px-1"
                    @submit.prevent="submitCreate"
                >
                    <label class="block text-[12px] text-os-sub">
                        カード名
                        <input
                            v-model="createForm.name"
                            type="text"
                            required
                            class="mt-1 block w-full rounded-lg border border-os-line px-3 py-2 text-[13px] focus-visible:ring-2 focus-visible:ring-os-yoyu/40 focus-visible:outline-none"
                        />
                    </label>
                    <label class="block text-[12px] text-os-sub">
                        発行元（任意）
                        <input
                            v-model="createForm.issuer_name"
                            type="text"
                            class="mt-1 block w-full rounded-lg border border-os-line px-3 py-2 text-[13px] focus-visible:ring-2 focus-visible:ring-os-yoyu/40 focus-visible:outline-none"
                        />
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="text-[12px] text-os-sub">
                            締日
                            <input
                                v-model="createForm.closing_day"
                                type="text"
                                required
                                class="mt-1 block w-full rounded-lg border border-os-line px-3 py-2 text-[13px] focus-visible:ring-2 focus-visible:ring-os-yoyu/40 focus-visible:outline-none"
                            />
                        </label>
                        <label class="text-[12px] text-os-sub">
                            支払日
                            <input
                                v-model="createForm.payment_day"
                                type="text"
                                required
                                class="mt-1 block w-full rounded-lg border border-os-line px-3 py-2 text-[13px] focus-visible:ring-2 focus-visible:ring-os-yoyu/40 focus-visible:outline-none"
                            />
                        </label>
                    </div>
                    <label class="block text-[12px] text-os-sub">
                        当月請求（円・任意）
                        <input
                            v-model="createForm.current_statement_minor"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            class="mt-1 block w-full rounded-lg border border-os-line px-3 py-2 text-[13px] focus-visible:ring-2 focus-visible:ring-os-yoyu/40 focus-visible:outline-none"
                        />
                    </label>
                    <label class="block text-[12px] text-os-sub">
                        未確定利用（円・任意）
                        <input
                            v-model="createForm.unconfirmed_minor"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            class="mt-1 block w-full rounded-lg border border-os-line px-3 py-2 text-[13px] focus-visible:ring-2 focus-visible:ring-os-yoyu/40 focus-visible:outline-none"
                        />
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
    </MoneyPageShell>
</template>
