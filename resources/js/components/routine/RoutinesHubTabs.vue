<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useCurrentUrl } from '@/composables/useCurrentUrl';

type HubTab = {
    label: string;
    href: string;
    matchPrefix?: boolean;
};

/**
 * ハブ導線:
 * プログラムを確認 → ルーティン（実行） → 今日/作戦 → 履歴
 *
 * 見た目は PageViewTabs と同じ下線タブ。URL / ページ遷移は従来どおり
 *（Inertia Link。同一ページ内パネル切替ではない）。
 *
 * 実施項目（ステップで使う部品の整理画面）は主導線外。
 * ルーティン編集の「ステップを追加」から作るのが基本。
 * サイドバー着地は /routines のまま（ナビ変更禁止）。
 */
const tabs: HubTab[] = [
    { label: 'プログラム', href: '/programs', matchPrefix: true },
    { label: 'ルーティン', href: '/routines', matchPrefix: true },
    { label: '今日/作戦', href: '/today', matchPrefix: true },
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
            class="relative -mb-px min-w-0 flex-1 px-1.5 py-2.5 text-center font-sans text-xs font-medium whitespace-nowrap transition-colors sm:flex-none sm:px-4 sm:text-sm"
            :class="
                isActive(tab)
                    ? 'text-primary'
                    : 'text-cd-ink-muted hover:text-cd-ink'
            "
        >
            {{ tab.label }}
            <span
                v-if="isActive(tab)"
                class="absolute inset-x-1 bottom-0 h-0.5 rounded-full bg-primary sm:inset-x-2"
                aria-hidden="true"
            />
        </Link>
    </nav>
</template>
