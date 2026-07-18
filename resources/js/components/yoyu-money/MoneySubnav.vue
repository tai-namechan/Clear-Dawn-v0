<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface Props {
    active: string;
}

defineProps<Props>();

const links: Array<{ key: string; label: string; href: string }> = [
    { key: 'dashboard', label: 'ダッシュボード', href: '/yoyu/money' },
    { key: 'cashflows', label: '入出金', href: '/yoyu/money/cashflows' },
    { key: 'accounts', label: '口座', href: '/yoyu/money/accounts' },
    { key: 'cards', label: 'カード', href: '/yoyu/money/cards' },
    { key: 'loans', label: 'ローン', href: '/yoyu/money/loans' },
    { key: 'transactions', label: '明細', href: '/yoyu/money/transactions' },
    { key: 'imports', label: 'CSV', href: '/yoyu/money/imports' },
    { key: 'analysis', label: '分析', href: '/yoyu/money/analysis' },
    { key: 'simulations', label: 'シミュレーター', href: '/yoyu/money/simulations' },
    { key: 'decisions', label: '判断履歴', href: '/yoyu/money/decisions' },
    { key: 'settings', label: '設定', href: '/yoyu/money/settings' },
];
</script>

<template>
    <nav
        aria-label="お金の余裕メニュー"
        class="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1 md:flex-wrap md:overflow-visible"
    >
        <Link
            v-for="link in links"
            :key="link.key"
            :href="link.href"
            class="shrink-0 rounded-full border px-3 py-1.5 text-[12.5px] font-semibold transition-colors"
            :class="
                active === link.key
                    ? 'border-os-yoyu bg-os-yoyu-soft text-os-yoyu'
                    : 'border-os-line bg-white text-os-sub hover:border-os-yoyu/40 hover:text-os-ink'
            "
            :aria-current="active === link.key ? 'page' : undefined"
        >
            {{ link.label }}
        </Link>
    </nav>
</template>
