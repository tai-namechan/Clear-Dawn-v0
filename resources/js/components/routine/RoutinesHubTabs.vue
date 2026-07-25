<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useCurrentUrl } from '@/composables/useCurrentUrl';

type HubTab = {
    label: string;
    href: string;
    matchPrefix?: boolean;
};

/**
 * ルーティン領域の横導線: 実行ハブ ↔ 履歴。
 * プログラム一覧はサイドバー / ルーティン画面ヘッダーから辿る（ハブタブには置かない）。
 */
const tabs: HubTab[] = [
    { label: 'ルーティン', href: '/routines', matchPrefix: true },
    { label: '履歴', href: '/history' },
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
        class="flex w-full gap-0.5 overflow-x-auto border-b border-cd-line [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
    >
        <Link
            v-for="tab in tabs"
            :key="tab.href"
            :href="tab.href"
            :aria-current="isActive(tab) ? 'page' : undefined"
            class="relative -mb-px min-w-0 flex-1 px-1.5 py-2.5 text-center font-sans text-xs whitespace-nowrap transition-colors md:flex-none md:px-5 md:text-sm"
            :class="
                isActive(tab)
                    ? 'bg-primary/8 font-semibold text-primary'
                    : 'font-medium text-cd-ink-muted hover:text-cd-ink'
            "
        >
            {{ tab.label }}
            <span
                v-if="isActive(tab)"
                class="absolute inset-x-1 bottom-0 h-0.5 rounded-full bg-primary md:inset-x-2"
                aria-hidden="true"
            />
        </Link>
    </nav>
</template>
