<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Button } from '@/components/ui/button';
import MoneySubnav from '@/components/yoyu-money/MoneySubnav.vue';
import { formatYen } from '@/lib/yoyuMoney/format';
import type { MoneyCardRow } from '@/lib/yoyuMoney/types';

interface Props {
    cards: MoneyCardRow[];
}

defineProps<Props>();

const createForm = reactive({
    name: '',
    issuer_name: '',
    closing_day: '15',
    payment_day: '10',
    current_statement_minor: '',
    unconfirmed_minor: '',
    available_minor: '',
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
            available_minor: createForm.available_minor || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                createForm.name = '';
                createForm.issuer_name = '';
                createForm.current_statement_minor = '';
                createForm.unconfirmed_minor = '';
                createForm.available_minor = '';
            },
        },
    );
}

defineOptions({
    layout: {
        title: 'ヨユウ',
        subtitle: 'カード',
    },
});
</script>

<template>
    <div class="mx-auto max-w-[720px] space-y-4">
        <Head title="カード — お金の余裕" />

        <MoneySubnav active="cards" />

        <section
            class="rounded-[18px] border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-3 text-sm font-bold text-os-ink">カードを追加</h2>
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
                    発行元
                    <input
                        v-model="createForm.issuer_name"
                        type="text"
                        class="mt-1 block w-36 rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                    />
                </label>
                <label class="text-[12px] text-os-sub">
                    締日
                    <input
                        v-model="createForm.closing_day"
                        type="text"
                        required
                        class="mt-1 block w-16 rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                    />
                </label>
                <label class="text-[12px] text-os-sub">
                    支払日
                    <input
                        v-model="createForm.payment_day"
                        type="text"
                        required
                        class="mt-1 block w-16 rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                    />
                </label>
                <label class="text-[12px] text-os-sub">
                    当月請求（円）
                    <input
                        v-model="createForm.current_statement_minor"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        class="mt-1 block w-28 rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                    />
                </label>
                <label class="text-[12px] text-os-sub">
                    未確定利用（円）
                    <input
                        v-model="createForm.unconfirmed_minor"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        class="mt-1 block w-28 rounded-lg border border-os-line bg-white px-2 py-1.5 text-[13px] text-os-ink outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/30"
                    />
                </label>
                <Button type="submit" size="sm" class="rounded-full">
                    追加
                </Button>
            </form>
            <p class="mt-2 text-[12px] text-os-sub">
                カード枠は保有資金に加算されません。
            </p>
        </section>

        <section
            class="rounded-[18px] border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
        >
            <h2 class="mb-3 text-sm font-bold text-os-ink">カード一覧</h2>
            <p v-if="cards.length === 0" class="text-[13px] text-os-sub">
                カードがまだありません。
            </p>
            <ul v-else class="space-y-3">
                <li
                    v-for="card in cards"
                    :key="card.id"
                    class="rounded-xl border border-os-line px-4 py-3"
                >
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p class="font-bold text-os-ink">{{ card.name }}</p>
                            <p class="text-[12px] text-os-sub">
                                {{ card.issuer_name || '発行元未設定' }} · 締日
                                {{ card.closing_day }} · 支払日
                                {{ card.payment_day }}
                            </p>
                        </div>
                        <span
                            v-if="!card.is_active"
                            class="text-[11px] font-semibold text-os-sub"
                        >
                            停止中
                        </span>
                    </div>
                    <dl
                        class="mt-3 grid grid-cols-2 gap-2 text-[13px] sm:grid-cols-3"
                    >
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
                            <dt class="text-[11px] text-os-sub">未確定</dt>
                            <dd class="font-semibold text-os-ink">
                                {{
                                    formatYen(card.unconfirmed?.amountMinor)
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[11px] text-os-sub">利用可能枠</dt>
                            <dd class="font-semibold text-os-ink">
                                {{ formatYen(card.available?.amountMinor) }}
                            </dd>
                        </div>
                    </dl>
                </li>
            </ul>
        </section>
    </div>
</template>
