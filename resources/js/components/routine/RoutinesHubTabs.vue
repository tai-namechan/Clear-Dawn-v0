<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useCurrentUrl } from '@/composables/useCurrentUrl';

type HubTab = {
    label: string;
    href: string;
    matchPrefix?: boolean;
    primary?: boolean;
};

/**
 * 主導線: メニューを作る → 今日やる → 履歴
 * 実施項目は部品ライブラリ（下位）として末尾に置く
 */
const tabs: HubTab[] = [
    { label: 'メニュー', href: '/routines', matchPrefix: true, primary: true },
    { label: '今日やる', href: '/today', matchPrefix: true },
    { label: '履歴', href: '/history' },
    { label: '部品', href: '/routine-items' },
];

const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

function isActive(tab: HubTab): boolean {
    if (tab.href === '/routines') {
        return (
            isCurrentOrParentUrl('/routines') &&
            !isCurrentOrParentUrl('/routine-items')
        );
    }

    if (tab.matchPrefix) {
        return isCurrentOrParentUrl(tab.href);
    }

    return isCurrentUrl(tab.href);
}
</script>

<template>
    <nav
        aria-label="ルーティンハブ"
        class="flex flex-wrap gap-2 border-b border-cd-line pb-3"
    >
        <Link
            v-for="tab in tabs"
            :key="tab.href"
            :href="tab.href"
            :aria-current="isActive(tab) ? 'page' : undefined"
            class="rounded-full border px-4 py-1.5 font-sans text-sm font-medium transition-colors"
            :class="
                isActive(tab)
                    ? tab.primary
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-primary/40 bg-primary/10 text-primary'
                    : 'border-cd-line bg-white text-cd-ink-muted hover:border-primary/30 hover:bg-primary/5 hover:text-primary'
            "
        >
            {{ tab.label }}
        </Link>
    </nav>
</template>
