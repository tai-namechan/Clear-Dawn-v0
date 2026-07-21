<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import MoneyAmount from '@/components/yoyu-money/MoneyAmount.vue';

type Candidate = {
    id: string;
    type: string;
    title: string;
    detail: string;
    amount_minor: string | null;
    href: string;
    simulate_href: string;
};

interface Props {
    candidates: Candidate[];
}

defineProps<Props>();
</script>

<template>
    <section
        class="rounded-2xl border border-os-line bg-white p-5 shadow-[0_1px_3px_rgba(38,48,58,0.05)]"
    >
        <h2 class="mb-3 text-sm font-bold text-os-ink">余裕を増やせる候補</h2>

        <p
            v-if="candidates.length === 0"
            class="text-[13px] text-os-sub"
        >
            現在の登録内容では、見直し候補はありません
        </p>

        <ul
            v-else
            class="space-y-3"
        >
            <li
                v-for="item in candidates"
                :key="item.id"
                class="rounded-xl bg-os-yoyu-bg/70 px-3.5 py-3"
            >
                <p class="text-[13px] font-semibold text-os-ink">
                    {{ item.title }}
                </p>
                <p class="mt-1 text-[12px] text-os-sub">
                    {{ item.detail }}
                    <template v-if="item.amount_minor">
                        （目安
                        <MoneyAmount :amount-minor="item.amount_minor" />
                        ）
                    </template>
                </p>
                <div class="mt-2 flex flex-wrap gap-3">
                    <Link
                        :href="item.href"
                        class="text-[12px] font-semibold text-os-yoyu hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/40"
                    >
                        内容を見る
                    </Link>
                    <Link
                        :href="item.simulate_href"
                        class="text-[12px] font-semibold text-os-yoyu hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-os-yoyu/40"
                    >
                        比較する
                    </Link>
                </div>
            </li>
        </ul>
    </section>
</template>
