<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import MoneyAmount from '@/components/yoyu-money/MoneyAmount.vue';

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

interface Props {
    events: TimelineEvent[];
    moreHref?: string;
}

withDefaults(defineProps<Props>(), {
    moreHref: '/yoyu/money/cashflows',
});

function formatShortDate(isoDate: string): string {
    const parts = isoDate.split('-');

    if (parts.length !== 3) {
        return isoDate;
    }

    return `${Number(parts[1])}/${Number(parts[2])}`;
}
</script>

<template>
    <section
        class="rounded-2xl border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
    >
        <div class="mb-3 flex items-center justify-between gap-2">
            <h2 class="text-sm font-bold text-os-ink">今後の残高</h2>
            <Link
                :href="moreHref"
                class="text-[12px] font-semibold text-os-yoyu hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/40"
            >
                今月の予定をすべて見る →
            </Link>
        </div>

        <p
            v-if="events.length === 0"
            class="text-[13px] text-os-sub"
        >
            表示できる予定はまだありません。収入や支払いを登録すると、日付順の残高が表示されます。
        </p>

        <ul
            v-else
            class="divide-y divide-os-line"
        >
            <li
                v-for="event in events"
                :key="event.id"
                class="grid grid-cols-[3.5rem_1fr_auto] items-center gap-2 py-2.5 text-[13px] sm:grid-cols-[4rem_1fr_7rem_7rem]"
                :class="event.is_shortfall ? 'bg-[#FBF6F1]' : ''"
            >
                <span class="tabular-nums text-os-sub">
                    {{ formatShortDate(event.due_on) }}
                </span>
                <div class="min-w-0">
                    <p class="truncate font-semibold text-os-ink">
                        {{ event.name }}
                    </p>
                    <p
                        v-if="event.is_shortfall"
                        class="text-[11px] text-[#8A5A3B]"
                    >
                        この時点で残高が不足する見込みです
                    </p>
                </div>
                <span class="hidden text-right sm:inline">
                    <MoneyAmount
                        :amount-minor="event.signed_amount_minor"
                        signed
                    />
                </span>
                <span class="text-right font-semibold">
                    <span class="mr-1 text-[11px] font-normal text-os-faint sm:hidden">
                        残高
                    </span>
                    <MoneyAmount :amount-minor="event.balance_after_minor" />
                </span>
            </li>
        </ul>
    </section>
</template>
